<?php

/**
 * Description of get-table-definition
 * 
 * @author Jakob Jentschke
 * @since 03.03.2015
 */

function getTableDefinition($createStatement, $prefix) {
    $table = array();
    
    $match = array();
    if(!preg_match('/^CREATE TABLE `(.*?)`\s*\((.*)\)\s*(.*)/is', $createStatement, $match)) return false;
    
    if(preg_match('/^'.$prefix.'/', $match[1])) $table['name'] = substr($match[1], strlen($prefix));
    else $table['name'] = $match[1];
    
    $table['columns'] = array();
    $table['keys'] = array();
    
    preg_match_all("/(?:\([^\)]*\)|'[^']*'|\"[^\"]*\"|[^,])+/", $match[2], $columns, PREG_SET_ORDER);
    foreach($columns as $column) {
        if(preg_match('/^\s*`(.*?)`\s*(.*)\s*$/is', $column[0]))  $table['columns'][] = trim($column[0]);
        else $table['keys'][] = trim($column[0]);
    }
    
    $table['options'] = $match[3];
    
    return $table;
}

function prepareTable(PDO $connection, $prefix, $table, $savedTable = NULL) {
    $alterStatements = array();
    
    if(isset($savedTable)) {
        $usedIds = array();
        
        $topId = 1;
        foreach($savedTable['columns'] as $col) if(getColumnId($col) > $topId) $topId = getColumnId($col);
        foreach($table['columns'] as $col) if(getColumnId($col) > $topId) $topId = getColumnId($col);
        
        foreach($table['columns'] as $column) {
            if(!getColumnId($column)) {
                $name = getColumnName($column);

                foreach($savedTable['columns'] as $savedColumn) {
                    if(getColumnName($savedColumn) == $name) {
                        $id = getColumnId($savedColumn);
                        
                        if(in_array($id, $usedIds)) return false;
                        $alterStatements[] = "MODIFY ".setColumnId($column, $id);
                        $usedIds[] = $id;
                        continue 2;
                    }
                }
                
                $topId++;
                if(in_array($topId, $usedIds)) return false;
                $alterStatements[] = "MODIFY ".setColumnId($column, $topId);
                $usedIds[] = $topId;
            }
        }
    }
    else {
        $id = 1;
        foreach($table['columns'] as $column) $alterStatements[] = "MODIFY ".setColumnId($column, $id++);
    }
    
    $stmt = $connection->prepare("ALTER TABLE ".$prefix.$table['name']." ".implode(',', $alterStatements));
    $stmt->execute();
}

function saveTableDefinition(PDO $connection, $prefix, $tableDefinition) {
    $stmt = $connection->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(array($prefix.$tableDefinition['name']));
    if($stmt->rowCount() == 1) {
        $alterStatements = array();
        
        $stmt = $connection->prepare("SHOW CREATE TABLE ".$prefix.$tableDefinition['name']);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $dbTable = getTableDefinition($row[1], $prefix);
        $modifiedIds = array();
        
        foreach($tableDefinition['columns'] as $column) {
            $id = getColumnId($column);
            if($dbColumn = getColumnById($dbTable, $id)) {
                $modifiedIds[] = $id;
                if($dbColumn != $column) $alterStatements[] = "CHANGE `".getColumnName($dbColumn)."` ".$column;
            }
            else $alterStatements[] = "ADD ".$column;
        }
        foreach($dbTable['columns'] as $column) {
            if(!in_array(getColumnId($column), $modifiedIds)) $alterStatements[] = "DROP ".getColumnName($column);
        }
        
        $stmt = $connection->prepare("SHOW INDEX FROM ".$prefix.$tableDefinition['name']);
        $stmt->execute();
        $droppedIndices = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if(!in_array($row['Key_name'], $droppedIndices)) {
		if($row['Key_name'] == 'PRIMARY') $alterStatements[] = "DROP PRIMARY KEY";
                else $alterStatements[] = "DROP INDEX ".$row['Key_name'];
                $droppedIndices[] = $row['Key_name'];
            }
        }
        
        foreach($tableDefinition['keys'] as $key) $alterStatements[] = "ADD ".$key;
        
        $alterStatements[] = $tableDefinition['options'];
        
        $stmt = $connection->prepare("ALTER TABLE ".$prefix.$tableDefinition['name']." ".implode(',', $alterStatements));
        $stmt->execute();
    }
    else {
        $query = "CREATE TABLE `".$prefix.$tableDefinition['name']."` (".
                implode(',', array_merge($tableDefinition['columns'], $tableDefinition['keys'])).
                ") ".$tableDefinition['options'];
        $stmt = $connection->prepare($query);
        $stmt->execute();
    }
}

function getColumnById($tableDefinition, $id) {
    foreach($tableDefinition['columns'] as $column) if(getColumnId($column) == $id) return $column;
    return false;
}

function getColumnName($columnDefinition) {
    if(preg_match('/^\s*`(.*?)`\s*(.*)\s*$/is', $columnDefinition, $match)) return $match[1];
    else return false;
}

function getColumnComment($columnDefinition) {
    if(preg_match("/^\s*`.*?`.*?COMMENT '(.*?)'\s*$/is", $columnDefinition, $match)) return $match[1];
    else return '';
}

function getColumnId($columnDefinition) {
    if(preg_match("/::(\d+)::/", getColumnComment($columnDefinition), $matchId)) return $matchId[1];
    else return false;
}

function setColumnComment($columnDefinition, $comment) {
    if(preg_match("/^\s*`.*?`.*?COMMENT '(.*?)'\s*$/is", $columnDefinition)) return preg_replace("/COMMENT '(.*?)'/is", "COMMENT '$comment'", $columnDefinition);
    else return $columnDefinition." COMMENT '$comment'";
}

function setColumnId($columnDefinition, $id) {
    $comment = getColumnComment($columnDefinition);
    if(preg_match("/::(\d+)::/", $comment, $matchId)) return setColumnComment($columnDefinition, preg_replace("/::(\d+)::/", "::".$id."::", $comment));
    else return setColumnComment($columnDefinition, "::".$id."::".$comment);
}


?>