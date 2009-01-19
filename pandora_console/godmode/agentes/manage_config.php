<?php

// Pandora FMS - the Flexible Monitoring System
// ============================================
// Copyright (c) 2008 Artica Soluciones Tecnologicas, http://www.artica.es
// Please see http://pandora.sourceforge.net for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

// Load global vars
require_once ("include/config.php");

check_login ();

if (! give_acl ($config['id_user'], 0, "LM")) {
	audit_db ($config['id_user'], $REMOTE_ADDR, "ACL Violation",
		"Trying to access Agent Config Management Admin section");
	require ("general/noaccess.php");
	return;
}


$id_group = (int) get_parameter ("id_group");
$origen = (int) get_parameter_post ("origen", -1);
$update_agent = (int) get_parameter ("update_agent", -1);
$update_group = (int) get_parameter ("update_group", -1);
$destino = (array) get_parameter_post ("destino", array ());
$origen_modulo = (array) get_parameter_post ("origen_modulo", array ());

// Operations
// ---------------

// DATA COPY
// ---------
if (isset($_POST["copy"])) {
	echo "<h2>".__('Data Copy')."</h2>";
	
	if (empty ($destino)) {
		echo '<h3 class="error">ERROR: '.__('No selected agents to copy').'</h3>';
		return;
	} 
	
	if (empty ($origen_modulo)) {
		echo '<h3 class="error">ERROR: '.__('No modules have been selected').'</h3>';
		return;
	}
	
	$copy_modules = (bool) get_parameter ('modules');
	$copy_alerts = (bool) get_parameter ('alerts');
	
	if (! $copy_alerts && ! $copy_modules) {
		echo '<h3 class="error">ERROR: '.__('You must check modules and/or alerts to be copied').'</h3>';
		return;
	}
	
	require_once ("include/functions_alerts.php");
	
	$origin_name = get_agent_name ($origen);
	
	// Copy
	$errors = 0;
	$id_new_module = 0;
	process_sql ("SET AUTOCOMMIT = 0;");
	process_sql ("START TRANSACTION;");
	
	foreach ($origen_modulo as $id_module) {
		//For each selected module
		$module = get_db_row ("tagente_modulo", "id_agente_modulo", $id_module);
		
		foreach ($destino as $id_agent_dest) {
			//For each destination agent
			$destiny_name = get_agent_name ($id_agent_dest);
			
			if ($copy_modules) {
				echo '<br /><br />'.__('Copying module').'<b> ['.$origin_name.' - '.$module["nombre"].'] -> ['.$destiny_name.']</b>';
				$sql = sprintf ('INSERT INTO tagente_modulo 
					(id_agente, id_tipo_modulo, descripcion,
					nombre, max, min, module_interval,
					tcp_port, tcp_send, tcp_rcv, 
					snmp_community, snmp_oid, ip_target,
					id_module_group, flag, id_modulo,
					disabled, id_export, 
					plugin_user, plugin_pass,
					plugin_parameter, id_plugin,
					post_process, prediction_module,
					max_timeout) 
					VALUES (%d, %d, "%s", "%s", %f, %f, %d,
					%d, "%s", "%s", "%s", "%s", "%s", %d,
					%d, %d, %d, %d, "%s", "%s", "%s", %d,
					%f, %d, %d)',
					$id_agent_dest, $module["id_tipo_modulo"],
					$module["descripcion"],
					$module["nombre"], $module["max"],
					$module["min"],
					$module["module_interval"],
					$module["tcp_port"],
					$module["tcp_send"],
					$module["tcp_rcv"],
					$module["snmp_community"],
					$module["snmp_oid"],
					get_agent_address ($id_agent_dest),
					$module["id_module_group"],
					$module["flag"],
					$module["id_modulo"],
					$module["disabled"],
					$module["id_export"],
					$module["plugin_user"],
					$module["plugin_pass"],
					$module["plugin_parameter"],
					$module["id_plugin"],
					$module["post_process"],
					$module["prediction_module"],
					$module["max_timeout"]);
				
				$id_new_module = process_sql ($sql, "insert_id");
				if (empty ($id_new_module)) {
					$errors++;
				} else {
					switch ($module["id_tipo_modulo"]) {
					case 2:
					case 6:
					case 9:
					case 100:
					case 21:
					case 18:
						$sql = sprintf ("INSERT INTO tagente_estado (id_agente_modulo, datos, timestamp, cambio, estado, id_agente, utimestamp) 
							VALUES (%d, 0,'0000-00-00 00:00:00',0,0, %d, 0)", $id_new_module, $id_agent_dest);
						break;
					default:
						$sql = sprintf ("INSERT INTO tagente_estado (id_agente_modulo, datos, timestamp, cambio, estado, id_agente, utimestamp) 
							VALUES (%d, 0,'0000-00-00 00:00:00',0,100, %d, 0)", $id_new_module, $id_agent_dest);
					}
					$result = process_sql ($sql);
					if ($result === false)
						$errors++;
				}//If empty id_new_module
			} //If modulos
			
			if ($copy_alerts) {
				if (empty ($id_new_module)) {
					//If we didn't copy modules or if we
					//didn't create new modules we have to
					//look for the module id
					$sql = sprintf ('SELECT id_agente_modulo
						FROM tagente_modulo
						WHERE nombre = "%s"
						AND id_agente = %d',
						$module['nombre'], $id_agent_dest);
					$id_new_module = get_db_sql ($sql);
					if (empty ($id_new_module))
						// We can't find a module belonging to this agent
						continue;
				}
				
				$alerts = get_db_all_rows_field_filter ('talert_template_modules',
					'id_agent_module', $id_module);
				if (empty ($alerts))
					// The module doesn't have alerts
					continue;
				
				foreach ($alerts as $alert) {
					echo '<br />'.__('Copying alert').'<b> ['.$origin_name.' - '.$module["nombre"].'] -> ['.$destiny_name.']</b>';
					if (!empty ($alert["id_agent"])) {
						//Compound alert
						$alert["id_agent"] = $id_agent_dest;
					}
					$values = array ('id_agent_module' => (int) $id_new_module,
						'id_alert_template' => (int) $alert['id_alert_template']);
					$id_alert = process_sql_insert ('talert_template_modules',
						$values);
					
					if ($id_alert === false) {
						$errors++;
						continue;
					}
					
					$actions = get_alert_agent_module_actions ($alert['id']);
					if (empty ($actions))
						continue;
					foreach ($actions as $action) {
						$values = array ('id_alert_template_module' => (int) $id_alert,
							'id_alert_action' => (int) $action['id'],
							'fires_min' => (int) $action['fires_min'],
							'fires_max' => (int) $action['fires_max']);
						$result = process_sql_insert ('talert_template_module_actions',
							$values);
						if ($result === false)
							$errors++;
					}
					
					/* TODO: Copy compound alerts */
					
				} //foreach alert
			} //if alerts
		} //Foreach destino
	} //Foreach origen_modulo
	if ($errors > 1) {
		echo '<h3 class="error">'.__('There was an error copying the module, the copy has been cancelled').'</h3>';
		process_sql ("ROLLBACK;");
	} else {
		echo '<h3 class="suc">'.__('Successfully copied module').'</h3>';
		process_sql ("COMMIT;");
	}
	process_sql ("SET AUTOCOMMIT = 1;");
	return; //Page shouldn't continue anymore
} //end of copy modules or alerts

// -----------
// DELETE DATA
// -----------
if (isset ($_POST["delete"])) {
	echo "<h2>".__('Agent Module Data Deletion')."</h2>";

	if (empty ($destino)) {
		echo '<h3 class="error">ERROR: '.__('No selected agents to copy').'</h3>';
		return;
	}

	if (empty ($origen_modulo)) {
		echo '<h3 class="error">ERROR: '.__('No modules have been selected').'</h3>';
		return;
	}
		
	// If selected modules or alerts
	if (isset($_POST["alerts"])) {
		$alertas = 1;
	} else {
		$alertas = 0;
	}
	
	if (isset($_POST["modules"])) {
		$modulos = 1;
		$alertas = 1;
	} else {
		$modulos = 0;
	}

	if (($alertas + $modulos) == 0){
		echo '<h3 class="error">ERROR: '.__('You must check modules and/or alerts to be deleted').'</h3>';
		return;
	}

	// Deletion
	// ---- 
	$errors = 0;
	
	process_sql ("SET AUTOCOMMIT = 0;");
	process_sql ("START TRANSACTION;"); //Start a transaction

	function temp_sql_delete ($table, $row, $value) {
		global $errors; //Globalize the errors variable
		$sql = sprintf ("DELETE FROM %s WHERE %s = %s", $table, $row, $value);
		
		$result = process_sql ($sql);
		
		if ($result === false)
			$errors++;
	}

	foreach ($origen_modulo as $id_module_src) {
		$nombre_src = get_db_value ("nombre", "tagente_modulo", "id_agente_modulo", $id_module_src);
		
		foreach ($destino as $agent_dest) {
			$sql = sprintf ("SELECT id_agente_modulo FROM tagente_modulo WHERE nombre = '%s' AND id_agente = %d", $nombre_src, $agent_dest);
			$id_module_dest = get_db_sql ($sql);
			if ($id_module_dest === false)
				continue; //If we don't have a module like that in the agent, then don't try deleting

			if ($alertas == 1) {
				//Alert
				/* TODO: Delete compound alerts */
				
				temp_sql_delete ('talert_template_modules', "id_agent_module", $id_module_dest);
			}
			
			if ($modulos == 1) {
				//Standard data
				temp_sql_delete ("tagente_datos", "id_agente_modulo", $id_module_dest);
	
				//Incremental Data
				temp_sql_delete ("tagente_datos_inc", "id_agente_modulo", $id_module_dest);

				//String data
				temp_sql_delete ("tagente_datos_string", "id_agente_modulo", $id_module_dest);
				
				//Events (up/down monitors)
				temp_sql_delete ("tevento", "id_agentmodule", $id_module_dest);
			
				//Graphs, layouts & reports
				temp_sql_delete ("tgraph_source", "id_agent_module", $id_module_dest);
				temp_sql_delete ("tlayout_data", "id_agente_modulo", $id_module_dest);
				temp_sql_delete ("treport_content", "id_agent_module", $id_module_dest);

				//The status of the module
				temp_sql_delete ("tagente_estado", "id_agente_modulo", $id_module_dest);

				//The actual modules, don't put anything based on
				//tagente_modulo after this
				temp_sql_delete ("tagente_modulo", "id_agente_modulo", $id_module_dest);
			} //if modulos
		} //foreach destino
	} //foreach origen_modulo
	
	if ($errors > 1) {
		echo '<h3 class="error">'.__('There was an error removing the module data, the removal has been cancelled').'</h3>';
		process_sql ("ROLLBACK;");
	} else {
		echo '<h3 class="suc">'.__('Successfully removed module data').'</h3>';
		process_sql ("COMMIT;");
	}
	process_sql ("SET AUTOCOMMIT = 1;");
	return; //Page shouldn't continue anymore
} //if $_POST['delete']

// -----------
// DELETE AGENT
// -----------

if (isset ($_POST["delete_agent"])) {
	echo "<h2>".__('Deleting Agent')."</h2>";
	// Initial checkings
	
	//  if selected more than 0 agents
	$destino = get_parameter_post ("destino", array ());
	
	if (empty ($destino)) {
		echo '<h3 class="error">ERROR: '.__('You must select at least one agent to be removed').'</h3>';
		return;
	}
	
	$result = delete_agent ($destino);	
	
	if ($result === false) {
		echo '<h3 class="error">'.__('There was an error removing the agents. Removal has been cancelled').'</h3>';
	} else {
		echo '<h3 class="suc">'.__('Successfully removed agents').'</h3>';
	}

	return;
}
																		
	
// ============	
// Form view
// ============
		
// title
echo '<h2>'.__('Agent configuration'). ' &gt; '. __('Configuration Management').'</h2>';
echo '<form method="post" action="index.php?sec=gagente&sec2=godmode/agentes/manage_config&operacion=1">';
echo '<table width="650" border="0" cellspacing="4" cellpadding="4" class="databox">';
	
// Source group
echo '<tr><td class="datost"><b>'. __('Source group'). '</b><br /><br />';
$groups = get_user_groups ($config['id_user']);
	
print_select ($groups, "id_group", $id_group, 'javascript:this.form.submit();', '', 0, false, false, false, '" style="width:200px');
echo '<noscript>&nbsp;&nbsp;';
print_submit_button (__('Filter'), "update_group", false, 'class="sub upd"');
echo '</noscript><br /><br />';
	
// Source agent
echo '<b>'. __('Source agent').'</b><br /><br />';

// Show combo with SOURCE agents
if ($id_group > 1) { //Group -1, 0 and 1 all mean that we should select ALL
	$result = get_db_all_rows_field_filter ("tagente", "id_grupo", $id_group, "nombre");
} else {
	$result = get_db_all_rows_in_table ("tagente", "nombre");
}
	
if ($result === false) {
	$result = array ();
	$result[0]["id_grupo"] = 0;
	$result[0]["id_agente"] = 0;
	$result[0]["nombre"] = __('No Agents in this Group');
}

$agents = array ();
foreach ($result as $row) {
	if (give_acl ($config["id_user"], $row["id_grupo"], "AR"))
		$agents[$row["id_agente"]] = $row["nombre"];
}

if ($origen == -1 || ($id_group > 1 && dame_id_grupo ($origen) != $id_group)) {
	$origen = $result[0]["id_agente"]; 
	//If the agent selected is not in the group selected (that
	//happens if an agent was selected and then the group was changed) 
}

print_select ($agents, "origen", $origen, 'javascript:this.form.submit();', '', 0, false, false, false, '" style="width:200px');
echo '<noscript>&nbsp;&nbsp;';
print_submit_button (__('Get Info'), "update_agent", false, 'class="sub upd"');
echo '</noscript><br /><br />';
	
// Source Module(s)
$result = get_db_all_rows_field_filter ("tagente_modulo", "id_agente", $origen, "nombre");
$modules = array ();

if ($result === false) {
	$result = array ();
	$result[0]["id_agente_modulo"] = -1;
	if ($origen > 0) {
		$result[0]["nombre"] = __('No modules for this agent');
	} else {
		$result[0]["nombre"] = __('No agent selected');
	}
}     
foreach ($result as $row) {
	$modules[$row["id_agente_modulo"]] = $row["nombre"];
}
	
echo '<b>'.__('Modules').'</b><br /><br />';
print_select ($modules, "origen_modulo[]", '', '', '', 0, false, true, false, '" style="width:250px'); 
echo '</td>';
		
echo '<td class="datost">';
echo '<b>'.__('Targets'). '</b>';
pandora_help ('manageconfig');
echo '<br /><br />';
echo '<table>';
echo '<tr><td class="datos">'.__('Modules').'</td><td class="datos">';
print_checkbox_extended ("modules", "1", false, false, '', 'class="chk"');

echo '</td></tr><tr><td class="datos">'.__('Alerts').'<td class="datos">';
print_checkbox_extended ("alerts", "1", false, false, '', 'class="chk"');
echo '</td></tr></table></td></tr>';
		

// Destination agent
$result = get_db_all_rows_in_table ("tagente", "nombre");
$agents = array ();
if ($result === false) {
	$result = array ();
}

foreach ($result as $row) {
	if (give_acl ($config["id_user"], $row["id_grupo"], "AW"))
		$agents[$row["id_agente"]] = $row["nombre"];
}

echo '<tr><td class="datost">';
echo '<b>'.__('To Agent(s):').'</b><br /><br />';
print_select ($agents, "destino[]", $destino, '', '', 0, false, true, false, '" style="width:250px');
echo '</td>';

// Form buttons
echo '<td align="left" class="datosb">';
echo "<br /><br />";
print_submit_button (__('Copy Modules/Alerts'), "copy", false, 'class="sub copy" onClick="if (!confirm("'.__('Are you sure?').'")) return false;"');
pandora_help ('manageconfig');
echo "<br /><br />";
print_submit_button (__('Delete Modules/Alerts'), "delete", false, 'class="sub delete" onClick="if (!confirm("'.__('Are you sure you want to delete these modules and alerts?').'")) return false;"');
pandora_help ('manageconfig');
echo "<br /><br />";
print_submit_button (__('Delete Agents'), "delete_agent", false, 'class="sub delete" onClick="if (!confirm("'.__('Are you sure you want to delete these agents?').'")) return false;"');
pandora_help ('manageconfig');
echo '</td></tr>';
echo '</table>';

?>
