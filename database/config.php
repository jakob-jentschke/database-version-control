<?php

/**
 * Description of structure
 * 
 *
 * @author Jakob Jentschke
 * @since 25.01.2015
 */

?>

<div class="content-left">
<?php

$handle = opendir('data');
while($package = readdir($handle)) {
    if(is_dir('data/'.$package)) {
        if(file_exists('data/'.$package.'/package.xml')) {
            $xml = simplexml_load_file('data/'.$package.'/package.xml');
            
            foreach($xml->table as $table) {
                if(isset($table->option)) {
                    echo "<a href=\"".$_SERVER['PHP_SELF']."?page=".$page."&package=".$package."\">".$package."</a><br/>";
                    break;
                }
            }
        }
    }
}

?>
</div>
<div class="content-right">
<?php

$db = new Database($configDB[0]['host'], $configDB[0]['user'], $configDB[0]['password'], $configDB[0]['database']);
$connection = $db->connect();

if(isset($_GET['package'])){
    $package = $_GET['package'];
    
    $data = array();
    
    $xml = simplexml_load_file('data/'.$package.'/package.xml');
    
    if(file_exists('data/'.$package.'/data.json')) $data = json_decode(file_get_contents('data/'.$package.'/data.json'), true);
    
    echo "
    <form method=\"post\">";
    
    foreach($xml->table as $table) {
        $tableName = (string) $table['name'];
        
        if(isset($table->option)) {
            foreach($table->option as $option) {
                if($option['type'] == 'select') {
                   if(isset($data[$tableName])) {
                         if(isset($_POST['save'])) {
                            $stmt = $connection->prepare("DELETE FROM `".$configDB['prefix']."vcs_config`
                                WHERE `package` = ?
                                AND `table` = ?
                                AND `option` = ?");
                            $stmt->execute(array($package, $tableName, $option['name']));
                            
                            $stmt = $connection->prepare("INSERT INTO `".$configDB['prefix']."vcs_config` (`package`, `table`, `option`, `value`) VALUES (?, ?, ?, ?)");
                                    
                            foreach($data[$tableName] as $id => $row) {
                                if($id == 'auto_increment' || $id == 'references') continue;
                                
                                if(in_array($id, $_POST[(string) $option['name']])) $stmt->execute(array($package, $tableName, $option['name'], $id.'->1'));
                                else  $stmt->execute(array($package, $tableName, $option['name'], $id.'->0'));
                            }
                        }
                        
                        
                        $stmt = $connection->prepare("SELECT `value` FROM `".$configDB['prefix']."vcs_config`
                                WHERE `package` = ?
                                AND `table` = ?
                                AND `option` = ?");
                        $stmt->execute(array($package, $tableName, $option['name']));
                        
                        $select = array();
                        while($row = $stmt->fetch()) {
                            if(preg_match('/^(\d+)->(\d+)$/', trim($row['value']), $matches)) {
                                $select[$matches[1]] = $matches[2];
                            }
                        }
                        
                        echo "
        <div>Auswahl ".$tableName."</div>";
                        foreach($data[$tableName] as $id => $row) {
                            if($id == 'auto_increment' || $id == 'references' || $id == 'options') continue;
                            
                            $checked = "";
                            if(isset($select[$id])) {
                                if($select[$id] == 1) $checked = " checked=\"checked\"";
                            }
                            else {
                                if(isset($option['default']) && $option['default'] == 1) $checked = " checked=\"checked\"";
                            }
                            echo "
        <input type=\"checkbox\" name=\"".$option['name']."[]\" value=\"".$id."\"".$checked.">".$row[(string) $option]."<br/>";
                        }
                    }
                }
            }
        }
    }
    echo "<br/>
        <input type=\"submit\" name=\"save\" value=\"speichern\"/>
    </form>";
}

?>
</div>

