<?php

/**
 * Réécriture d'un fichier zip provenant d'un export de notion au format .md
 * L'objectif est de retirer les hashs dans le nom des fichiers / dossiers 
 * et récrire le contenu des fichiers pour associer les nouveaux liens
 * 
 * @author: LECOMTE Cyril
 * @since : 2023-03-04
 */

//------------------------------------------------------------
// Déclaration des constantes
//------------------------------------------------------------
define('TMP_DIR', 'tmp');
define('PREFIX_NEW_ZIP', 'new_');


//------------------------------------------------------------
// Déclaration des fonctions
//------------------------------------------------------------
/**
 * Extrait le zip vers le dossier temporaire
 * @param $file <string> Chemin du fichier à décompresser
 * 
 * @return <bool> true si décompression / false sinon
 */
function extractZip(string $file): bool {
    if(file_exists($file)) {
        // Etape 1 : Création d'un répertoire pour la décompression du zip d'origine
        $zip = new ZipArchive;
        if($zip->open($file) === true) {
            $zip->extractTo(TMP_DIR);
            $zip->close();
            return true;
        }
    }
    return false;
}

/**
 * Renommage des fichiers depuis le dossier temporaire
 * 
 * @return <array> liste des modifications
 */
function renameAllFiles():array {
    $renaming = [];
    if(file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP_DIR.'/'));
        foreach($files as $file) {
            // Ignorer les répertoires "." et ".."
            if(!in_array($file->getBasename(), array('.', '..'))) {
                $currentFile = $file->getBasename();
                // si il y a une partie MD5 dans le nom du fichier / dossier
                if(preg_match('/.*(\s[a-f0-9]{32})/', $currentFile)) {
                    $fileTmp = pathinfo(changeName($file->getPath(), $currentFile));
                    $renaming[$currentFile] = $fileTmp['basename'];
                }
            }
        }
    }
    return $renaming;
}

/**
 * Renommage des dossiers depuis le dossier temporaire
 * 
 * @return <array> liste des modifications
 */
function renameAllDirectories():array {
    $renaming = [];
    if(file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP_DIR.'/'), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($dirs as $dir) {
            // Ignorer les répertoires "." et ".."
            if(!in_array($dir->getBasename(), array('.', '..'))) {
                if($dir->isDir()) {
                    // si il y a une partie MD5 dans le nom du fichier / dossier
                    if(preg_match('/.*(\s[a-f0-9]{32})/', $dir->getBasename())) {
                        $fileTmp = pathinfo( changeName($dir->getPath(), $dir->getBasename()));
                        $renaming[$dir->getBasename()] = $fileTmp['basename'];
                    }
                }
            }
        }
    }
    return $renaming;
}

/**
 * Extrait le zip vers le dossier temporaire
 * @param $file <string> Chemin du fichier à décompresser
 * 
 * @return <bool> true si décompression / false sinon
 */
function createNewZip(string $sourceZip):bool
{
    $fileParts = pathinfo($sourceZip);
    $zipname = trim($fileParts['dirname'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.PREFIX_NEW_ZIP.$fileParts['basename'];
    // Créer une instance de la classe ZipArchive
    $zip = new ZipArchive();
    // Ouvrir le fichier ZIP en mode création
    if($zip->open($zipname, ZipArchive::CREATE) === true) {
        // Parcourir les fichiers du répertoire
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP_DIR));
        foreach($files as $file) {            
            // Ignorer les répertoires "." et ".."
            if(!in_array($file->getBasename(), array('.', '..'))) {
                $filePath = $file->getRealPath();
                // Ajouter le fichier dans le ZIP
                $zip->addFile($filePath, substr($filePath, strpos($filePath, TMP_DIR)+(strlen(TMP_DIR)+1)));
            }
        }
        // On ferme le fichier ZIP
        $zip->close();
        return true;
    } 
    return false;
}

/**
 * Suppression d'un répertoire complet contenant des fichiers/dossiers
 * @param $dir <string> Chemin du dossier à supprimer
 */
function deleteDirectory(string $dir):void {
    $files = array_diff(scandir($dir), array('.','..'));
    // On supprimer tous les fichiers dans le répertoire
    foreach ($files as $file) {
        $current = $dir.DIRECTORY_SEPARATOR.$file;
        (is_dir($current)) ? deleteDirectory($current) : unlink($current);
    }
    rmdir($dir);
}


function changeName(string $path, string $oldName, int $i = 0):string {
    $i++;
    if(preg_match('/.*(\s[a-f0-9]{32})/', $oldName, $replace)) {
        // si on a récupérer une partie de MD5
        if(isset($replace) && is_array($replace) && isset($replace[1])) {
            $newName = $path.DIRECTORY_SEPARATOR.str_replace($replace[1], ($i==1 ? '':' '.$i), $oldName);
            if(file_exists($newName)) {
                // On ajoutera un chiffre au nom du fichier si il existe déjà
                return changeName($path, $oldName, $i);
            }
            else {
                rename($path.DIRECTORY_SEPARATOR.$oldName, $newName);
            }
        }
    }
    return str_replace(TMP_DIR.DIRECTORY_SEPARATOR,'', $newName);
}

// Réadapate dans le format souhaité
function formatLinkToMD($data) {
    return str_replace(         
        ['+', '%2F','%28', '%29'],
        ['%20','/','(',')'],
        urlencode($data)
    );
}

function changeContentFiles($renaming) {
    if(file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP_DIR.'/'));
        foreach($files as $file) {
            if($file->isFile()) {
                // On modifie le contenu
                file_put_contents(
                    $file, 
                    str_replace(array_keys($renaming), array_values($renaming), file_get_contents($file))
                );
            }            
        }
    }
}

//------------------------------------------------------------
// Début du code à executer
//------------------------------------------------------------

// On récupére la liste des fichiers zip du répertoire courant
$files = glob('./*.zip');
$renaming = [];
// On boucle sur l'ensemble des fichiers ZIP 
foreach($files as $file) {   
    if(extractZip($file)) {
        $renaming = array_merge(renameAllFiles(), renameAllDirectories());
        var_dump($renaming);
        // Si on a renommé des fichiers
        if(sizeof($renaming)) {
            // Réécrire le contenu des fichiers avec les nouvelles adresses
            $renamingForMD = [];
            foreach($renaming as $key => $value) {
                $renamingForMD[formatLinkToMD($key)] = formatLinkToMD($value);
            } 
            // Modifier le contenu des fichiers
            changeContentFiles($renamingForMD);
        }
        // Dans tout les cas on recompresse le contenu du répertoire
        createNewZip($file);
        // suppression du dossier temporaire
        if(is_dir(TMP_DIR)) deleteDirectory(TMP_DIR);
    }
}
