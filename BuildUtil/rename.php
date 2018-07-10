<?php
/**
 * Created by IntelliJ IDEA.
 * User: john
 * Date: 7/9/2018
 * Time: 11:48 PM
 */

$path = "/cygdrive/e/develnext-17/ide/src/ide/project/behaviours/ZimbraLog";

exec("ls ".$path,$files);
foreach($files as $file){

    $fileArray = explode("Backup",$file);
    if(count($fileArray)>1){
//        var_dump($fileArray);
        $newfileame = "ZimbraLog".$fileArray[1];
        $command = "mv ".$path."/".$file." ".$path."/".$newfileame;
        echo($command)."\n";
    }

}
//var_dump($files);