/* Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

$('#bt_importEqLogic').on('click', function () {
  jeedom.eqLogic.getSelectModal({}, function (result) {
    $.ajax({// fonction permettant de faire de l'ajax
      type: "POST", // méthode de transmission des données au fichier php
      url: "plugins/sonybravia/core/ajax/sonybravia.ajax.php", // url du fichier php
      data: {
        action: "copyFromEqLogic",
        eqLogic_id: result.id,
        id: $('#ul_eqLogic .li_eqLogic.active').attr('data-eqLogic_id')
      },
      dataType: 'json',
      global: false,
      error: function (request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function (data) { // si l'appel a bien fonctionné
        if (data.state != 'ok') {
          $('#div_alert').showAlert({message: data.result, level: 'danger'});
          return;
        }
        $('#ul_eqLogic .li_eqLogic.active').click();
      }
    });
  });
});

function maj_etat(){
	$.ajax({// fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/sonybravia/core/ajax/sonybravia.ajax.php", // url du fichier php
		data: {
			action: "deamon_info",
			mac : $( "input[data-l1key='logicalId']" ).value()
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) { // si l'appel a bien fonctionné
			if (data.result == true) {
				$(".deamoninfo").removeClass('label-danger').addClass( 'label-success' );
			}
			else{
				$(".deamoninfo").removeClass('label-success').addClass( 'label-danger' );
			}
		}
	});
}

$('.deamoninfo').on('click', function () {
	maj_etat();
});

$( ".deamoninfo" ).on('mouseenter', function() {
  maj_etat();
});

$('#bt_cronGenerator').on('click',function(){
  jeedom.getCronSelectModal({},function (result) {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=autorefresh]').value(result.value);
  });
});

$("#bt_addsonybraviaInfo").on('click', function (event) {
  var _cmd = {type: 'info'};
  addCmdToTable(_cmd);
});

$("#bt_addsonybraviaAction").on('click', function (event) {
  var _cmd = {type: 'action'};
  addCmdToTable(_cmd);
});

$('#table_cmd tbody').delegate('tr .remove', 'click', function (event) {
  $(this).closest('tr').remove();
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
  var el = $(this);
  jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
    calcul.atCaret('insert', result.human);
  });
});

$("#table_cmd").delegate(".listEquipementAction", 'click', function () {
  var el = $(this);
  var subtype = $(this).closest('.cmd').find('.cmdAttr[data-l1key=subType]').value();
  jeedom.cmd.getSelectModal({cmd: {type: 'action', subType: subtype}}, function (result) {
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.attr('data-input') + ']');
    calcul.atCaret('insert', result.human);
  });
});

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  if (init(_cmd.logicalId) == 'refresh') {
    return;
  }

  if (init(_cmd.type) == 'info') {
    var disabled = (init(_cmd.configuration.sonybraviaAction) == '1') ? 'disabled' : '';
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '" sonybraviaAction="' + init(_cmd.configuration.sonybraviaAction) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id"></span>';
    tr += '</td>';

    tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"></td>';

    tr += '<td>';
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';

    tr += '<td>';
    tr += '<select style="width : 200px;" class="cmdAttr form-control input-sm" data-l1key="logicalId">';
    tr += '<option value="model">Model</option>';
    tr += '<option value="status">Etat</option>';
    tr += '<option value="volume">Volume</option>';
    tr += '<option value="source">Source</option>';
    tr += '<option value="chaine">Chaine</option>';
    tr += '<option value="nom_chaine">Nom Chaine</option>';
    tr += '<option value="program">Programme</option>';
    tr += '<option value="debut_p">Début du programme</option>';
    tr += '<option value="fin_p">Fin du programme</option>';
    tr += '<option value="pourcent_p">Pourcentage d\'achèvement</option>';
    tr += '<option value="duree">Durée</option>';
    tr += '<option value="sources">Liste des sources</option>';
    tr += '<option value="apps">Liste des applications</option>';
    tr += '<option value="ircc_commands">Liste des commandes IRCC</option>';
    tr += '</select>';
    tr += '</td>';

    tr += '<td></td>'

    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr expertModeVisible" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span><br/>';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
  }

  if (init(_cmd.type) == 'action') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id"></span>';
    tr += '</td>';

    tr += '<td>';
    tr += '<div class="row">';
    tr += '<div class="col-sm-6">';
    tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>';
    tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
    tr += '</div>';
    tr += '<div class="col-sm-6">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
    tr += '</div>';
    tr += '</div>';
    tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;margin-right : 10px;" title="{{La valeur de la commande vaut par défaut la commande}}">';
    tr += '<option value="">Aucune</option>';
    tr += '</select>';
    tr += '</td>';

    tr += '<td>';
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '<input class="cmdAttr" data-l1key="configuration" data-l2key="sonybraviaAction" value="1" style="display:none;" >';
    tr += '</td>';

    tr += '<td><select style="width : 200px;" class="cmdAttr form-control input-sm" data-l1key="logicalId">';
    tr += '<option value="turn_on">Allumer</option>';
    tr += '<option value="turn_off">Eteindre</option>';
    tr += '<option value="volume_up">Vol haut</option>';
    tr += '<option value="volume_down">Vol bas</option>';
    tr += '<option value="mute_volume">Mute</option>';
    tr += '<option value="select_source">Changer Source</option>';
    tr += '<option value="start_app">Démarrer une application</option>';
    tr += '<option value="play_content">Jouer un fichier</option>';
    tr += '<option value="media_play">Lecture</option>';
    tr += '<option value="media_pause">Pause</option>';
    tr += '<option value="media_previous_track">Précédent</option>';
    tr += '<option value="media_next_track">Suivant</option>';
    tr += '<option value="start_app">Démarrer une application</option>';
    tr += '<option value="ircc">Code ICCR</option>';
    tr += '<option value="command">Commande IRCC</option>';
    tr += '</select></td>';

    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="param" placeholder="{{Nom information}}" style="margin-bottom : 5px;width : 70%; display : inline-block;" />';
    tr += '</td>';
    tr += '<td>';
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="updateCmdId" style="display : none;margin-top : 5px;" title="Commande d\'information à mettre à jour">';
    tr += '<option value="">Aucune</option>';
    tr += '</select>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    tr += '</td>';

    tr += '<td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    var tr = $('#table_cmd tbody tr:last');
    jeedom.eqLogic.builSelectCmd({
      id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
      filter: {type: 'info'},
      error: function (error) {
			  $('#div_alert').showAlert({message: error.message, level: 'danger'});
      },
      success: function (result) {
        tr.find('.cmdAttr[data-l1key=value]').append(result);
        tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
        tr.setValues(_cmd, '.cmdAttr');
        jeedom.cmd.changeType(tr, init(_cmd.subType));
      }
    });
  }
}

function Pairing() {
  var self = this;

  self.onPairSuccess = function() {
    bootbox.prompt("{{Veuillez indiquer le code affiché sur la TV}}", function (result) {
      if ((result == null) || (result.length == 0)) {
        self.onPinEnteredError();
      } else {
        self.onPinEnteredSuccess(result);
      }
    });
  };

  self.setPsk = function(psk) {
    $("input[data-l2key='psk']" ).value(psk);
  };

  self.getPsk = function() {
    return $("input[data-l2key='psk']" ).value();
  };

  self.onFailure = function(status) {
    var msg = "{{Procédure d\'appairage échouée}}";
    var level = "danger";

    if (status == 1) {
      msg = "Impossible de se connecter à la TV, vérifiez la configuration";
    } else if (status == 2) {
      msg = "Impossible d'allumer automatiquement la TV, vérifiez la configuration";
    } else if (status == 3) {
      self.setPsk("");
      msg = "Impossible de s'authentifier, vérifiez la configuration";
    } else if (status == 4) {
      msg = "Le periphérique est déjà appairé, le code PIN n'est plus nécessaire";
      level = "warning";
    }
		$('#div_alert').showAlert({message: msg, level: level});
  };

  self.onPinEnteredError = function() {
    $('#div_alert').showAlert({message: '{{Le code PIN ne peut être vide}}', level: 'danger'});
  };

  self.onPinEnteredSuccess = function(pin) {
    self.setPsk(pin);
    self.confirm();
  };

  self.pair = function() {
    $.ajax({
			type: "POST",
			url: "plugins/sonybravia/core/ajax/sonybravia.ajax.php",
			data: {
				action: "pairing",
				ip   : $("input[data-l2key='ipadress']" ).value(),
				mac  : $("input[data-l1key='logicalId']" ).value(),
        name : $("input[data-l1key='name']" ).value()
			},
			dataType: 'json',
			error: function (request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(data) {
        if (data.state != 'ok') {
          self.onFailure(data.result.status);
        } else {
          self.onPairSuccess();
        }
      }
    });
  };

  self.confirm = function() {
    $.ajax({
			type: "POST",
			url: "plugins/sonybravia/core/ajax/sonybravia.ajax.php",
			data: {
				action: "confirm",
				ip   : $("input[data-l2key='ipadress']" ).value(),
				mac  : $("input[data-l1key='logicalId']" ).value(),
				psk  : self.getPsk(),
        name : $("input[data-l1key='name']" ).value()
			},
			dataType: 'json',
			error: function (request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(data) {
        if (data.state != 'ok') {
          self.onFailure(data.result.status);
        } else {
          self.onConfirmSuccess();
        }
      }
    });
  };

  self.onConfirmSuccess = function() {
    $("[data-action='save']").click();
  };

  self.init = function() {
    $(".show_pin").on("click", function() {
      self.pair();
    });

    $("input[data-l1key='name']").on("keypress", function() {
      self.setPsk("");
    });
  };

  self.init();
};

$("document").ready(function() {
  var pair = new Pairing();
});
