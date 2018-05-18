#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import time
import threading
import queue
import signal
import argparse
import logging
import json
import requests
from braviarc import BraviaRC

class JeedomWorker(threading.Thread):
  def __init__(self, queue, jeedom_url, jeedom_key, tv_mac, delay=1):
    super().__init__()
    self.waitter = threading.Event()
    self.queue = queue
    self.delay = delay
    self.data  = {}
    self.jeedom_key = jeedom_key
    self.jeedom_url = jeedom_url
    self.tv_mac = tv_mac
    self.log = logging.getLogger(__name__)

  def run(self):
    self.waitter.clear()
    while not self.waitter.is_set():
      try:
        data = self.queue.get(block=False)
        self.handle(data)
      except queue.Empty:
        pass
      self.waitter.wait(self.delay)

  def stop(self):
    self.waitter.set()

  def handle(self, data):
    left = {}
    for key, val in data.items():
      if (key not in self.data) or (self.data[key] != val):
        left[key] = val
    if 0 != len(left):
      if True == self.send(left):
        self.data = data

  def send(self, changed):
    if "sources" in changed:
      changed["sources"] = "|".join(changed["sources"].keys())
    if "apps" in changed:
      changed["apps"] = "|".join(changed["apps"].keys())
    if "ircc_commands" in changed:
      changed["ircc_commands"] = "|".join(changed["ircc_commands"])

    params = {
      "mac" : self.tv_mac,
      "apikey" : self.jeedom_key,
    }

    try:
      payload = json.dumps(changed, ensure_ascii=False).encode("utf-8")
      self.log.info("sending changed values to jeedom url %s", self.jeedom_url)
      self.log.debug("changed values : %s", payload)
      r = requests.post(self.jeedom_url, data=changed, params=params, timeout=10)
      r.raise_for_status()
      return True
    except requests.HTTPError as err:
      self.log.error("jeedom http error: %s (%s)", str(err), r.content)
    except Exception as err:
      self.log.error("unknown error: %s", str(err))
    return False

class TvWorker(threading.Thread):
  def __init__(self, braviarc, queue, delay=2):
    super().__init__()
    self.waitter = threading.Event()
    self.braviarc = braviarc
    self.queue = queue
    self.delay = delay
    self.sources = {}
    self.apps = {}
    self.commands = {}
    self.log = logging.getLogger(__name__)

  def run(self):
    self.waitter.clear()
    while not self.waitter.is_set():
      try:
        data = self.fetch_data()
        self.queue.put(data)
      except Exception as error:
        self.log.warning("got runtime error: %s", str(error))
      self.waitter.wait(self.delay)

  def stop(self):
    self.waitter.set()

  def fetch_static(self):
    """
    sources and apps are 'cached' since:
    1. they're long to fetch
    2. they doesn't change often
    3. information is mostly unavailable (TV off)
    """
    if self.sources == {}:
      self.log.info("fetching source list")
      self.sources = self.braviarc.load_source_list()

    if self.apps == {}:
      self.log.info("fetching app list")
      self.apps = self.braviarc.load_app_list()

    if self.commands == {}:
      self.log.info("fetching command list")
      self.braviarc._refresh_commands()
      self.commands = [ x['name'] for x in self.braviarc._commands ]

  def fetch_data(self):
    res = {
      "program"       : "",
      "nom_chaine"    : "",
      "debut"         : "",
      "debut_p"       : "",
      "fin_p"         : "",
      "pourcent_p"    : "0",
      "duree"         : "",
      "chaine"        : "",
      "source"        : "",
      "model"         : "",
      "volume"        : "",
      "sources"       : self.sources,
      "apps"          : self.apps,
      "ircc_commands" : self.commands,
    }
    res["status"]  = self.braviarc.get_power_status()
    if res["status"] == "active":
      self.fetch_static()
      res["sources"]       = self.sources
      res["apps"]          = self.apps
      res["ircc_commands"] = self.commands

      info = self.braviarc.get_system_info()
      if info and "model" in info:
        res["model"] = info["model"]

      info = self.braviarc.get_volume_info()
      if info and "volume" in info:
        res["volume"] = info["volume"]

      res["source"] = "Application"
      play_info = self.braviarc.get_playing_info()
      if play_info:
        res["source"]     = play_info['source'][-4:].upper() + play_info['uri'][-1:]
        res["chaine"]     = play_info.get("dispNum", "")
        res["program"]    = play_info.get("programTitle", "")
        res["nom_chaine"] = play_info.get("title", "")
        res["duree"]      = play_info.get("durationSec", "0")
        res["debut"]      = play_info.get("startDateTime", "")
        if res["debut"] and res["duree"]:
          s, e, p, = self.braviarc.playing_time(play_info["debut"], res["duree"])
          res["debut_p"], res["fin_p"], res["pourcent_p"] = (s, e, p)
    return res

class BraviaApp:
  def parse_args(self):
    # common arguments
    parent = argparse.ArgumentParser(add_help=False)
    parent.add_argument("--jeedom-name", metavar="<str>", help="device name",           required=True)
    parent.add_argument("--tv-ip",       metavar="<ip>",  help="tv ip address",         required=True)
    parent.add_argument("--tv-mac",      metavar="<str>", help="tv mac address",        required=True)
    parent.add_argument("--log-level",   metavar="<str>", help="log level",             default="info")

    # subcommands parser
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(title="subcommands", help="available sub-commands:")

    # subcommand daemon
    daemon = subparsers.add_parser("daemon", parents=[parent], help="runs daemon listening to TV changes and pushes to given callback ")
    daemon.add_argument("--tv-pin",     metavar="<int>",  help="tv PIN",                required=True)
    daemon.add_argument("--jeedom-key", metavar="<str>",  help="jeedom api key",        required=True)
    daemon.add_argument("--jeedom-url", metavar="<url>",  help="jeedom callback url",   required=True)
    daemon.add_argument("--pid-file",   metavar="<path>", help="pid file path",         default=None)
    daemon.set_defaults(func=self.run_daemon)

    # subcommand pair
    pair = subparsers.add_parser("pair", parents=[parent], help="starts PIN pairing procedure")
    pair.set_defaults(func=self.run_pair)

    # subcommand pair
    confirm = subparsers.add_parser("confirm", parents=[parent], help="confirm PIN pairing procedure")
    confirm.add_argument("--tv-pin", metavar="<int>", help="tv PIN", required=True)
    confirm.set_defaults(func=self.run_confirm)

    # subcommand cmd
    cmd = subparsers.add_parser("cmd", parents=[parent], help="send commands to TV")
    cmd.add_argument("--tv-pin",  metavar="<int>",  help="tv PIN",            required=True)
    cmd.add_argument("--command", metavar="<str>",  help="command name",      required=True)
    cmd.add_argument("--param",   metavar="<str>",  help="command parameter", default=None)
    cmd.set_defaults(func=self.run_cmd)
    parser.parse_args(namespace=self)

  def execute(self):
    self.parse_args()
    self.init_logging()
    self.braviarc = BraviaRC(self.tv_ip, self.tv_mac)
    self.func()

  def run_daemon(self):
    self.connect(pairing=False)
    self.write_pidfile()
    signal.signal(signal.SIGTERM, lambda x,y: self.daemon_stop())
    self.daemon_start()
    self.daemon_join()

  def run_pair(self):
    self.tv_pin = "0000"
    self.connect(pairing=True)

  def run_confirm(self):
    self.connect(pairing=False)

  def run_cmd(self):
    if self.command == "turn_on":
      self.braviarc.turn_on(try_ircc=False)
      sys.exit(0)

    self.connect(auto=False)
    commands = {
      "turn_off"             : self.braviarc.turn_off,
      "volume_up"            : self.braviarc.volume_up,
      "volume_down"          : self.braviarc.volume_down,
      "media_play"           : self.braviarc.media_play,
      "media_pause"          : self.braviarc.media_pause,
      "media_previous_track" : self.braviarc.media_previous_track,
      "media_next_track"     : self.braviarc.media_next_track,
      "mute_volume"          : self.braviarc.mute_volume,
    }
    commands_arg = {
      'select_source' : self.braviarc.select_source,
      'set_volume'    : self.braviarc.set_volume_level,
      'start_app'     : self.braviarc.start_app,
      'play_content'  : self.braviarc.play_content,
      "command"       : self.braviarc.send_command,
      "ircc"          : self.cmd_ircc
    }
    if self.command in commands:
      self.log.info("running command: %s", self.command)
      commands[self.command]()
      sys.exit(0)
    if self.command in commands_arg:
      self.log.info("running parametric command: %s(%s)", self.command, self.param)
      commands_arg[self.command](self.param)
      sys.exit(0)
    self.log.error("unknown command: %s", self.command)
    sys.exit(1)

  def cmd_ircc(self, params):
    for code in params.split(";"):
      self.braviarc.send_req_ircc(code)
      time.sleep(0.25)

  def connect(self, awaked=False, pairing=False, auto=True):
    self.log.info("connecting to tv on %s", self.tv_ip)
    connected, powered, authorized = self.braviarc.connect(self.tv_pin, self.jeedom_name, self.jeedom_name)

    if not connected:
      if not awaked and auto:
        self.log.info("unable to connect to tv on %s, trying wake-on-lan on %s", self.tv_ip, self.tv_mac)
        self.braviarc._wakeonlan()
        time.sleep(20)
        return self.connect(awaked=True, pairing=pairing, auto=auto)
      self.log.info("still unable to connect to tv on %s after wake-on-lan", self.tv_ip)
      sys.exit(1)
    self.log.info("successfully connected to %s", self.tv_ip)

    if not powered:
      if not awaked and auto:
        self.log.info("tv is not powered, trying wake-on-lan on %s", self.tv_mac)
        self.braviarc._wakeonlan()
        time.sleep(20)
        return self.connect(awaked=True, pairing=pairing, auto=auto)
      self.log.error("tv still not powered after wake-on-lan on %s", self.tv_mac)
      sys.exit(2)
    self.log.info("tv is properly powered")

    if not authorized:
      if pairing:
        self.log.info("pairing request success, please register PIN at screen")
        sys.exit(0)
      self.log.error("could not register with tv %s with device=%s pin=%s ", self.tv_ip, self.jeedom_name, self.tv_pin)
      sys.exit(3)

    if pairing:
      self.log.error("pairing request failed, device %s is already registered with tv", self.jeedom_name)
      sys.exit(4)
    self.log.info("successfully authorized on tv %s ", self.tv_ip)

    if awaked and auto:
      self.log.info("shutting down tv since it was awakened")
      self.braviarc.turn_off()
      time.sleep(5)

  def daemon_start(self):
    self.log.info("starting daemon")
    data_queue = queue.Queue()
    self.jeedomWorker = JeedomWorker(data_queue,
                                     self.jeedom_url,
                                     self.jeedom_key,
                                     self.tv_mac)
    self.tvWorker = TvWorker(self.braviarc, data_queue)
    self.jeedomWorker.start()
    self.tvWorker.start()

  def daemon_stop(self):
    self.log.info("stopping daemon")
    self.jeedomWorker.stop()
    self.tvWorker.stop()

  def daemon_join(self):
    try:
      self.jeedomWorker.join()
      self.tvWorker.join()
    except KeyboardInterrupt:
      self.log.debug("received Ctrl-C, exiting threads")
      self.daemon_stop()
      self.daemon_join()

  def init_logging(self):
    level = logging.INFO
    self.log_level = self.log_level.lower()
    if self.log_level == "info":
      level = logging.INFO
    elif self.log_level == "error":
      level = logging.ERROR
    elif self.log_level == "debug":
      level = logging.DEBUG
    elif self.log_level == "warning":
      level = logging.WARN
    fmt = "%(asctime)s [%(levelname)s] %(message)s"
    if level == logging.DEBUG:
      fmt = "%(asctime)s [%(levelname)s] %(message)s (%(module)s in %(pathname)s:%(lineno)s)"
    logging.basicConfig(format=fmt, level=logging.ERROR, stream=sys.stdout)
    self.log = logging.getLogger(__name__)
    self.log.setLevel(level)

  def get_pidfile(self):
    if self.pid_file is None:
      return "/tmp/jeedom/sonybravia/sonybravia_%s.pid" % self.tv_mac.replace(":", "")
    return self.pid_file

  def write_pidfile(self):
    pidfile = self.get_pidfile()
    with open(pidfile, "w") as pid:
      pid.write("%s\n" % os.getpid())
      self.log.info("writing pid to file '%s'" % pidfile)

if __name__ == "__main__":
  app = BraviaApp()
  app.execute()
