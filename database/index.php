<?php
/**
 * Description of index
 * 
 *
 * @author Jakob Jentschke
 * @since 25.01.2015
 */


include_once('../site/config.php');
include_once('class/Database.php');


if (isset($_GET['page'])) {
    $page = $_GET['page'];
} else {
    $page = 1;
}

switch ($page) {
    case 1:
        $include = "version-control.php";
        $title = "Versionskontrolle";
        break;
    
    case 2:
        $include = "config.php";
        $title = "Konfiguration";
        break;

    default:
        $include = "version-control.php";
        $title = "Versionskontrolle";
        break;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>MAI - Datenbank-Versionskontrolle - <?= $title ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" type="text/css" href="css/style.css">
        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables.css">
        <!-- jQuery -->
        <script type="text/javascript" charset="utf8" src="http://ajax.aspnetcdn.com/ajax/jQuery/jquery-1.8.2.min.js"></script>
        <!-- DataTables -->
        <script type="text/javascript" charset="utf8" src="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/jquery.dataTables.min.js"></script>
    </head>
    <body>
        <div id="main">
            <div id="header">
                MAI - Datenbank-Versionskontrolle
            </div>
            <div id="menu">
                <ul>
                    <li <?= ($page == 1) ? "class=\"active\"" : "" ?>><a href="index.php?page=1">Versionskontrolle</a></li>
                    <li <?= ($page == 2) ? "class=\"active\"" : "" ?>><a href="index.php?page=2">Konfiguration</a></li>
                </ul>
            </div>
            <div id="menu-title">
                <?= strtoupper($title) ?>
            </div>
            <div id="content">
                <?php include_once $include; ?>
            </div>
            <div id="footer">Copyright © 2015 <a href="http://www.200grad.de">200grad GmbH & Co. KG</a></div>
        </div>
    </body>
</html>