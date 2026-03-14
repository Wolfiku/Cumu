<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/id3_reader.php';
if(!isLoggedIn())jsonErr('Unauthorized',401);
if($_SERVER['REQUEST_METHOD']!=='POST')jsonErr('POST only.',405);
if(empty($_FILES['files']))jsonErr('No files uploaded.');
$fallbackArtist=trim($_POST['artist']??'')?:'Unknown Artist';
$fallbackAlbum=trim($_POST['album']??'')?:'Unknown Album';
function sanitizeFolderName(string $n):string{$n=preg_replace('/[\/\\\\:*?"<>|]/','_',$n);return trim($n,". \t")?:'Unknown';}
$ext_ok=AUDIO_EXT;$uploaded=[];$errors=[];$db=getDB();
$files=$_FILES['files'];$count=is_array($files['name'])?count($files['name']):1;
for($i=0;$i<$count;$i++){
    $name=is_array($files['name'])?$files['name'][$i]:$files['name'];
    $tmp=is_array($files['tmp_name'])?$files['tmp_name'][$i]:$files['tmp_name'];
    $size=is_array($files['size'])?$files['size'][$i]:$files['size'];
    $err=is_array($files['error'])?$files['error'][$i]:$files['error'];
    if($err!==UPLOAD_ERR_OK){$errors[]="$name: Upload error $err";continue;}
    if($size>MAX_UPLOAD){$errors[]="$name: Too large (max 100 MB)";continue;}
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(!in_array($ext,$ext_ok,true)){$errors[]="$name: Unsupported format";continue;}
    $meta=readID3($tmp);
    $title=$meta['title']?:preg_replace('/^\d+[\s.\-_]+/','',pathinfo($name,PATHINFO_FILENAME))?:pathinfo($name,PATHINFO_FILENAME);
    $artist=$meta['artist']?:$fallbackArtist;
    $album=$meta['album']?:$fallbackAlbum;
    $track=$meta['track']?:0;
    $duration=$meta['duration'];
    $year=$meta['year']?:null;
    $coverPath=saveCoverArt($meta);
    $safeArtist=sanitizeFolderName($artist);$safeAlbum=sanitizeFolderName($album);
    $safeFile=sanitizeFolderName(pathinfo($name,PATHINFO_FILENAME)).'.'.$ext;
    $targetDir=MUSIC_DIR.'/'.$safeArtist.'/'.$safeAlbum;
    if(!is_dir($targetDir))mkdir($targetDir,0750,true);
    $destPath=$targetDir.'/'.$safeFile;
    if(file_exists($destPath)){$safeFile=sanitizeFolderName(pathinfo($name,PATHINFO_FILENAME)).'_'.time().'.'.$ext;$destPath=$targetDir.'/'.$safeFile;}
    if(!move_uploaded_file($tmp,$destPath)){$errors[]="$name: Could not save";continue;}
    if(!$coverPath){foreach(['cover.jpg','cover.jpeg','cover.png','folder.jpg'] as $cn){$src=$targetDir.'/'.$cn;if(file_exists($src)){if(!is_dir(COVERS_DIR))mkdir(COVERS_DIR,0750,true);$dst=COVERS_DIR.'/'.md5($src).'.'.pathinfo($src,PATHINFO_EXTENSION);if(!file_exists($dst))copy($src,$dst);$coverPath='covers/'.basename($dst);break;}}}
    $relPath=$safeArtist.'/'.$safeAlbum.'/'.$safeFile;
    try{
        $db->beginTransaction();
        $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artist]);
        $ar=$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1');$ar->execute([$artist]);$artistId=(int)$ar->fetchColumn();
        $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name,year) VALUES(?,?,?)')->execute([$artistId,$album,$year]);
        $alb=$db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1');$alb->execute([$artistId,$album]);$albumId=(int)$alb->fetchColumn();
        if($coverPath){$db->prepare('UPDATE albums SET cover=? WHERE id=? AND cover IS NULL')->execute([$coverPath,$albumId]);$db->prepare('UPDATE artists SET image=? WHERE id=? AND image IS NULL')->execute([$coverPath,$artistId]);}
        $db->prepare('INSERT OR IGNORE INTO songs(artist_id,album_id,title,path,duration,track_num) VALUES(?,?,?,?,?,?)')->execute([$artistId,$albumId,$title,$relPath,$duration,$track]);
        $db->commit();
        $uploaded[]=['name'=>$safeFile,'artist'=>$artist,'album'=>$album,'title'=>$title];
    }catch(Exception $e){if($db->inTransaction())$db->rollBack();@unlink($destPath);$errors[]="$name: DB error – ".$e->getMessage();}
}
jsonOk(['uploaded'=>$uploaded,'errors'=>$errors]);
