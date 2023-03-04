# NOTION IMPROVE

Lors d'un export au format MD ( Markdown) avec les sous pages et les images.
Notion extrait l'ensemble des données dans un zip, cependant dans ce zip, les fichiers ont un nom avec un hash, ceci permet à Notion de gérer les doublons 
de noms de fichiers et ou de dossiers, car il est effectivement possible dans notion d'écrire 2 pages ayant le même nom.


Mon systeme permet de réécrire le zip en renommant les fichiers et dossiers pour retirer les hash et de modifier le contenu de chacuns des fichiers 
afin de conserver le fonctionnement des liens et des sources d'images.

il suffit de lancer la commande : 
> php notion_zip_improve.php

ou d'executer le .bat si vous êtes sous Windows.

