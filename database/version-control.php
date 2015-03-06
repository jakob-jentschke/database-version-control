<?php

/**
 * Description of structure
 * 
 *
 * @author Jakob Jentschke
 * @since 25.01.2015
 */

include_once('functions.php');

function saveStructure($package, PDO $connection) {
    global $configDB;
    
    $structure = array();
    
    $xml = simplexml_load_file('data/'.$package.'/package.xml');
    
    if(isset($xml->package)) {
        foreach($xml->package as $pack) {
            saveStructure($pack['name'], $connection);
        }
    }
    
    foreach($xml->table as $table) {
        $tableName = (string) $table['name'];
        
        $stmt = $connection->prepare('SHOW CREATE TABLE `'.$configDB['prefix'].$tableName.'`');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);
        
        prepareTable($connection, $configDB['prefix'], getTableDefinition($row[1], $configDB['prefix']));
        
        $stmt = $connection->prepare('SHOW CREATE TABLE '.$configDB['prefix'].$tableName);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $structure[] = getTableDefinition($row[1], $configDB['prefix']);

        echo "<div class=\"msgOK\">Paket '".$package."': Struktur der Tabelle '".$tableName."' eingelesen</div>";
    }
    
    if(file_exists('data/'.$package.'/structure.json')) $oldStructure = json_decode(file_get_contents('data/'.$package.'/structure.json'), true);
    else $oldStructure = array();
    foreach($oldStructure as $key => $val) unset($oldStructure[$key]['create']);
    $compareStructure = $structure;
    foreach($compareStructure as $key => $val) unset($compareStructure[$key]['create']);
    
    if(!file_exists('data/'.$package.'/structure.json') || $oldStructure !== $compareStructure) {
        file_put_contents('data/'.$package.'/structure.json', json_encode($structure));
        echo "<div class=\"msgOK\">Paket '".$package."': Struktur wurde gespeichert</div>";
    }
    else echo "<div class=\"msgNotice\">Paket '".$package."': Es liegt keine Änderung der Struktur vor</div>";

    echo "<br/>";
}


function loadStructure($package, $connection) {
    global $configDB;
    
    $xml = simplexml_load_file('data/'.$package.'/package.xml');
    
    if(isset($xml->package)) {
        foreach($xml->package as $pack) {
            loadStructure($pack['name'], $connection);
        }
    }
    
    if(!file_exists('data/'.$package.'/structure.json')) {
        if(isset($xml->package)) echo "<div class=\"msgOK\">Paket '".$package."': Struktur wurde geladen</div>";
        else "<div class=\"msgError\">Paket '".$package."': Struktur wurde nicht gefunden</div>";
        return;
    }
    
    $structure = json_decode(file_get_contents('data/'.$package.'/structure.json'), true);

    foreach($structure as $table) {
	$stmt = $connection->prepare("SHOW TABLES LIKE '".$configDB['prefix'].$table['name']."'");
        $stmt->execute();
        if($stmt->rowCount() == 1) {
	  $stmt = $connection->prepare('SHOW CREATE TABLE `'.$configDB['prefix'].$table['name'].'`');
	  $stmt->execute();
	  $row = $stmt->fetch(PDO::FETCH_NUM);
	  
	  prepareTable($connection, $configDB['prefix'], getTableDefinition($row[1], $configDB['prefix']), $table);
        }
        
        saveTableDefinition($connection, $configDB['prefix'], $table);

        echo "<div class=\"msgOK\">Paket '".$package."': Struktur der Tabelle '".$table['name']."' geladen</div>";
    }
    echo "<div class=\"msgOK\">Paket '".$package."': Struktur wurde geladen</div><br/>";
}


function saveData($package, $connection) {
    global $configDB;
    
    $data = array();
    if(file_exists('data/'.$package.'/data.json'))$dataOld = json_decode(file_get_contents('data/'.$package.'/data.json'), true);
    else $dataOld = array();
    
    $xml = simplexml_load_file('data/'.$package.'/package.xml');
    
    if(isset($xml->package)) {
        foreach($xml->package as $pack) {
            saveData($pack['name'], $connection);
        }
    }

    foreach($xml->table as $table) {
        $tableName = (string) $table['name'];

        if(strtolower((string)$table['no_data']) == 'true') {
            echo "<div class=\"msgNotice\">Paket '".$package."': keine Datenspeicherung für Tabelle ".$tableName." vorgesehen</div>";
            continue;
        }

        $data[$tableName] = array();

        if(isset($table['auto_increment'])) $data[$tableName]['auto_increment'] = (string)$table['auto_increment'];
        
        if(isset($table->option)) {
            foreach($table->option as $option) {
                if($option['type'] == 'select') {
                    $stmt = $connection->prepare("SELECT `value` FROM `".$configDB['prefix']."vcs_config`
                            WHERE `package` = ?
                            AND `table` = ?
                            AND `option` = ?");
                    $stmt->execute(array($package, $tableName, $option['name']));

                    $select = array();
                    
                    if(isset($option['default']) && $option['default'] == 1) $default = 1;
                    else $default = 0;
                    
                    while($row = $stmt->fetch()) {
                        if(preg_match('/^(\d+)->(\d+)$/', trim($row['value']), $matches)) {
                            $select[$matches[1]] = $matches[2];
                        }
                    }
                    
                    if(isset($dataOld[$tableName])) {
                        foreach($dataOld[$tableName] as $id => $row) {
                            if(isset($select[$id])) {
                                if($select[$id] == 0) $data[$tableName][$id] = $row;
                            }
                            elseif($default == 0) $data[$tableName][$id] = $row;
                        }
                    }
                }
            }
        }

        if(isset($table->condition)) {
            $condition = false;
            foreach($table->condition as $cond) {
                if($condition) $condition .= " AND ".$cond;
                else $condition = $cond;
            }
            $condition = " WHERE ".$condition;
        }
        else $condition = "";

        $stmt = $connection->prepare("SELECT MAX(id_local) FROM ".$configDB['prefix']."vcs_data_ids WHERE `package` = ? AND `table` = ?");
        $stmt->execute(array($package, $table['name']));
        $dat = $stmt->fetch();
        $newId = intval($dat[0]) + 1;


        $stmt = $connection->prepare("SELECT `id_local`, `id_global` FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ?");
        $stmt->execute(array($package, $tableName));

        $idGlobal = array();
        while($row = $stmt->fetch()) $idGlobal[$row['id_local']] = $row['id_global'];

        $references = array();
        if(isset($table->reference)) {
            $data[$tableName]['references'] = array();
            foreach($table->reference as $reference) {
                $data[$tableName]['references'][(string) $reference] = (string) $reference['table'];

                $references[(string) $reference] = array();

                $stmt = $connection->prepare("SELECT `id_local`, `id_global` FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ?");
                $stmt->execute(array($package, $reference['table']));

                while($row = $stmt->fetch()) $references[(string) $reference][$row['id_local']] = $row['id_global'];
            }
        }
        
        if(isset($table->option)) {
            $data[$tableName]['options'] = array();
            foreach($table->option as $option) {
                $data[$tableName]['options'][] = array(
                    'name'      => (string) $option['name'],
                    'type'      => (string) $option['type'],
                    'default'   => (string) $option['default']
                );
            }
        }
        
        if(isset($table->query)) $stmt = $connection->prepare((string) $table->query);
        else $stmt = $connection->prepare("SELECT * FROM ".$configDB['prefix'].$tableName.$condition);
        $stmt->execute();
        
        $stmtInsert = $connection->prepare("INSERT INTO ".$configDB['prefix']."vcs_data_ids(`package`, `table`, `id_global`, `id_local`)
            VALUES(?, ?, ?, ?)");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach($references as $referenceName => $reference) {
                if($row[$referenceName] != 0) $row[$referenceName] = $reference[$row[$referenceName]];
            }
            
            if(isset($table['auto_increment'])) {
                $rowId = $row[(string)$table['auto_increment']];
                unset($row[(string)$table['auto_increment']]);

                if(isset($idGlobal[$rowId])) $data[$tableName][$idGlobal[$rowId]] = $row;
                else {
                    $data[$tableName][$newId] = $row;
                    $stmtInsert->execute(array($package, $tableName, $newId++, $rowId));
                }
            }
            else $data[$tableName][] = $row;
        }
        ksort($data[$tableName]);
        
        echo "<div class=\"msgOK\">Paket '".$package."': Daten der Tabelle '".$tableName."' eingelesen</div>";
    }
    
    if(!empty($data)) {
        if($data !== $dataOld) {
            file_put_contents('data/'.$package.'/data.json', json_encode($data));
            echo "<div class=\"msgOK\">Paket '".$package."': Daten wurden gespeichert</div><br/>";
        }
        else echo "<div class=\"msgNotice\">Paket '".$package."': Es liegen keine Änderungen der Daten vor</div><br/>";
    }
    else echo "<div class=\"msgNotice\">Paket '".$package."': keine Daten vorhanden</div><br/>";
}


function loadData($package, $connection) {
    global $configDB;
    
    $xml = simplexml_load_file('data/'.$package.'/package.xml');
    
    if(isset($xml->package)) {
        foreach($xml->package as $pack) {
            loadData($pack['name'], $connection);
        }
    }
    
    if(!file_exists('data/'.$package.'/data.json')) {
        if(isset($xml->package)) echo "<div class=\"msgOK\">Paket '".$package."': Daten wurden geladen</div>";
        else "<div class=\"msgError\">Paket '".$package."': Daten wurden nicht gefunden</div>";
        return;
    }
    
    $data = json_decode(file_get_contents('data/'.$package.'/data.json'), true);
    
    foreach($data as $tableName => $table) {
        if(isset($table['options'])) {
            foreach($table['options'] as $option) {
                if($option['type'] == 'select') {
                    $stmt = $connection->prepare("SELECT `value` FROM `".$configDB['prefix']."vcs_config`
                            WHERE `package` = ?
                            AND `table` = ?
                            AND `option` = ?");
                    $stmt->execute(array($package, $tableName, $option['name']));

                    $select = array();
                    
                    if(isset($option['default']) && $option['default'] == 1) $default = 1;
                    else $default = 0;
                    
                    while($row = $stmt->fetch()) {
                        if(preg_match('/^(\d+)->(\d+)$/', trim($row['value']), $matches)) {
                            $select[$matches[1]] = $matches[2];
                        }
                    }
                }
            }
        }
        
        
        $idLocal = array();
        $stmt = $connection->prepare("SELECT `".$configDB['prefix'].$tableName."`.`".$table['auto_increment']."` AS `local`, `".$configDB['prefix']."vcs_data_ids`.`id_global` AS `global`
                FROM `".$configDB['prefix']."vcs_data_ids`
                LEFT JOIN `".$configDB['prefix'].$tableName."` ON `".$configDB['prefix']."vcs_data_ids`.`id_local` = `".$configDB['prefix'].$tableName."`.`".$table['auto_increment']."`
                WHERE `".$configDB['prefix']."vcs_data_ids`.`package` = ? AND `".$configDB['prefix']."vcs_data_ids`.`table` = ?");
        $stmt->execute(array($package, $tableName));
        
        $stmtDelete = $connection->prepare("DELETE FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ? AND `id_global` = ?");

        while($row = $stmt->fetch()) {
            if($row['local'] === NULL) $stmtDelete->execute(array($package, $tableName, $row['global']));
            else $idLocal[$row['global']] = $row['local'];
        }
        $stmtInsert = $connection->prepare("INSERT INTO ".$configDB['prefix']."vcs_data_ids(`package`, `table`, `id_global`, `id_local`)
            VALUES(?, ?, ?, ?)");

        foreach($table as $idGlobal => $row) {
            if($idGlobal == 'auto_increment' || $idGlobal == 'references' || $idGlobal == 'options') continue;

            if(isset($select)) {
                if(isset($select[$idGlobal])) {
                    if($select[$idGlobal] == 0) continue;
                }
                elseif($default == 0) continue;
                
            }
            
            $references = array();
            if(isset($table['references'])) {
                foreach($table['references'] as $referenceName => $reference) {
                    $stmt = $connection->prepare("SELECT `id_local`, `id_global` FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ?");
                    $stmt->execute(array($package, $reference));

                    $references[$referenceName] = array();
                    while($dat = $stmt->fetch()) $references[$referenceName][$dat['id_global']] = $dat['id_local'];
                }
            }

            foreach($references as $referenceName => $reference) {
                if($row[$referenceName] != 0) $row[$referenceName] = $references[$referenceName][$row[$referenceName]];
            }

            $setData = array();
            foreach($row as $col => $val) {
                if($val !== NULL) $setData[] = "`".$col."` = '".mysql_real_escape_string($val)."'";
                else $setData[] = "`".$col."` = NULL";
            }
            
            if(isset($table['auto_increment'])) {
                if(isset($idLocal[$idGlobal])) {
                    $stmt2 = $connection->prepare("UPDATE `".$configDB['prefix'].$tableName."` SET ".implode(',', $setData)." WHERE `".$table['auto_increment']."` = ?");
                    $stmt2->execute(array($idLocal[$idGlobal]));
                    unset($idLocal[$idGlobal]);
                }
                else {
                    $stmt2 = $connection->prepare("INSERT INTO `".$configDB['prefix'].$tableName."` SET ".implode(',', $setData));
                    $stmt2->execute();
                    $stmtInsert->execute(array($package, $tableName, $idGlobal, $connection->lastInsertId()));
                }
            }
            else {
                $stmt2 = $connection->prepare("INSERT INTO `".$configDB['prefix'].$tableName."` SET ".implode(',', $setData));
                $stmt2->execute();
            }
        }

        if(isset($table['auto_increment'])) {
            $stmtDelete = $connection->prepare("DELETE FROM `".$configDB['prefix'].$tableName."` WHERE `".$table['auto_increment']."` = ?");
            $stmtDelete2 = $connection->prepare("DELETE FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ? AND `id_global` = ? AND `id_local` = ?");

            foreach($idLocal as $global => $local) {
                $stmtDelete->execute(array($local));
                $stmtDelete2->execute(array($package, $tableName, $global, $local));
            }
        }


        if(isset($table->condition)) {
            $condition = false;
            foreach($table->condition as $cond) {
                if($condition) $condition .= " AND ".$cond;
                else $condition = $cond;
            }
            $condition = " AND ".$condition;
        }
        else $condition = "";

        $stmt = $connection->prepare("DELETE FROM ".$configDB['prefix'].$tableName." WHERE `".$table['auto_increment']."` NOT IN (
                SELECT `id_local` FROM `".$configDB['prefix']."vcs_data_ids` WHERE `package` = ? AND `table` = ?) ".
                $condition);
        $stmt->execute(array($package, $tableName));

        echo "<div class=\"msgOK\">Paket '".$package."': Daten der Tabelle '".$tableName."' geladen</div>";
    }
    echo "<div class=\"msgOK\">Paket '".$package."': Daten wurden geladen</div><br/>";
}

?>

<form method="post">
    <div class="form-title">Pakete:</div>
    <br/>
<?php

$handle = opendir('data');
while($package = readdir($handle)) {
    if(is_dir('data/'.$package)) {
        if(file_exists('data/'.$package.'/package.xml')) {
            $xml = simplexml_load_file('data/'.$package.'/package.xml');
            
            if(isset($xml->package)) {
                $packs = array();
                foreach($xml->package as $pack) $packs[] = (string) $pack['name'];
                $includedPackages = " (".implode(', ', $packs).")";
            }
            else $includedPackages = "";
            
            if(isset($_POST['package']) && in_array($package, $_POST['package'])) $checked = " checked=\"checked\"";
            else $checked = "";
            echo "<input type=\"checkbox\" name=\"package[]\" value=\"".$package."\"".$checked."/>".$package.$includedPackages."<br/>";
        }
    }
}

if((isset($_POST['structure']) && $_POST['structure'] == 1) || !isset($_POST['send'])) $checkedStructure = " checked=\"checked\"";
else $checkedStructure = "";

if((isset($_POST['data']) && $_POST['data'] == 1) || !isset($_POST['send'])) $checkedData = " checked=\"checked\"";
else $checkedData = "";

?>
    <br/>
    <input type="checkbox" name="structure" value="1"<?php echo $checkedStructure; ?>/>Struktur
    <input type="checkbox" name="data" value="1"<?php echo $checkedData; ?>/>Daten
    <br/><br/>
    <input type="hidden" name="send" value="1"/>
    <input type="submit" name="save" value="speichern"/>
    <input type="submit" name="load" value="laden"/>
</form>

<div id="messageBox">
<?php

$db = new Database($configDB[0]['host'], $configDB[0]['user'], $configDB[0]['password'], $configDB[0]['database']);
$connection = $db->connect();
$stmt = $connection->prepare("SET NAMES 'utf8'");
$stmt->execute();

if(isset($_POST['save'])) {
    foreach($_POST['package'] as $package) {
        if(isset($_POST['structure'])) saveStructure($package, $connection);
        if(isset($_POST['data'])) saveData($package, $connection);
    }
}

if(isset($_POST['load']) && isset($_POST['package'])) {
    if(!is_dir('data/backup')) mkdir('data/backup');
    
    $filename = $filename = "data/backup/backup_".time().".sql";
    exec("mysqldump -u ".$configDB[0]['user']." -p'".$configDB[0]['password']."' --opt ".$configDB[0]['database']." > ".$filename);
    
    foreach($_POST['package'] as $package) {
        if(isset($_POST['structure'])) loadStructure($package, $connection);
        if(isset($_POST['data'])) loadData($package, $connection);
    }
}

?>
</div>
