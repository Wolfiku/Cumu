<?php
/**
 * CUMU – backend/id3_reader.php
 * Pure-PHP ID3v1 + ID3v2.2/2.3/2.4 reader. No dependencies.
 */
function readID3Tags(string $path): array
{
    $meta = ['title'=>'','artist'=>'','album'=>'','track'=>0,'year'=>0,'cover_data'=>null,'cover_ext'=>'jpg'];
    if (!is_readable($path)) return $meta;
    $fp = @fopen($path, 'rb');
    if (!$fp) return $meta;

    $header = fread($fp, 10);

    if (strlen($header) >= 10 && substr($header, 0, 3) === 'ID3') {
        $ver     = ord($header[3]);
        $tagSize = ((ord($header[6])&0x7f)<<21)|((ord($header[7])&0x7f)<<14)|((ord($header[8])&0x7f)<<7)|(ord($header[9])&0x7f);

        if ($tagSize > 0 && $tagSize < 30*1024*1024) {
            $tag = fread($fp, $tagSize);
            $pos = 0; $tl = strlen($tag);

            while ($pos < $tl - ($ver===2?6:10)) {
                if ($ver === 2) {
                    $fid = substr($tag,$pos,3); if($fid==="\x00\x00\x00") break;
                    $fsz = (ord($tag[$pos+3])<<16)|(ord($tag[$pos+4])<<8)|ord($tag[$pos+5]); $pos+=6;
                } else {
                    $fid = substr($tag,$pos,4); if($fid==="\x00\x00\x00\x00") break;
                    $fsz = $ver===4 ? ((ord($tag[$pos+4])&0x7f)<<21)|((ord($tag[$pos+5])&0x7f)<<14)|((ord($tag[$pos+6])&0x7f)<<7)|(ord($tag[$pos+7])&0x7f)
                                    : unpack('N',substr($tag,$pos+4,4))[1]; $pos+=10;
                }
                if ($fsz<=0||$pos+$fsz>$tl) break;
                $fd = substr($tag,$pos,$fsz); $pos+=$fsz;
                if (strlen($fd)<2) continue;
                $enc = ord($fd[0]);

                $txt = static function(string $s) use ($enc): string {
                    try { switch($enc) {
                        case 1: return trim(mb_convert_encoding($s,'UTF-8','UTF-16'),"\x00\xfe\xff ");
                        case 2: return trim(mb_convert_encoding($s,'UTF-8','UTF-16BE'),"\x00 ");
                        case 3: return trim($s,"\x00 ");
                        default: return trim(mb_convert_encoding($s,'UTF-8','ISO-8859-1'),"\x00 ");
                    }} catch(\Throwable){return trim($s,"\x00 ");}
                };

                $tmap=['TIT2'=>'title','TT2'=>'title','TPE1'=>'artist','TP1'=>'artist',
                       'TALB'=>'album','TAL'=>'album','TRCK'=>'track','TRK'=>'track',
                       'TDRC'=>'year','TYER'=>'year','TYE'=>'year'];
                if (isset($tmap[$fid])) {
                    $v=$txt(substr($fd,1)); $k=$tmap[$fid];
                    if($k==='track') $v=(int)explode('/',$v)[0];
                    if($k==='year')  $v=(int)substr($v,0,4);
                    if(!$meta[$k]) $meta[$k]=$v;
                } elseif (($fid==='APIC'||$fid==='PIC') && !$meta['cover_data']) {
                    try {
                        if ($fid==='PIC') {
                            $ext=strtolower(substr($fd,1,3))==='png'?'png':'jpg';
                            $de=strpos($fd,"\x00",5)??5; $img=substr($fd,$de+1);
                        } else {
                            $me=strpos($fd,"\x00",1); if($me===false) continue;
                            $mime=strtolower(substr($fd,1,$me-1)); $ext=str_contains($mime,'png')?'png':'jpg';
                            $ds=$me+2;
                            if($enc===1||$enc===2){$de=$ds;$dl=strlen($fd);while($de<$dl-1){if($fd[$de]==="\x00"&&$fd[$de+1]==="\x00"){$de+=2;break;}$de+=2;}}
                            else{$dn=strpos($fd,"\x00",$ds);$de=$dn===false?$ds:$dn+1;}
                            $img=substr($fd,$de);
                        }
                        if(strlen($img??'')>64){$meta['cover_data']=$img;$meta['cover_ext']=$ext;}
                    } catch(\Throwable){}
                }
            }
        }
    }

    // ID3v1 fallback
    @fseek($fp,-128,SEEK_END);
    $v1=fread($fp,128);
    if(strlen($v1)===128&&substr($v1,0,3)==='TAG'){
        $c=static fn($s)=>trim(mb_convert_encoding($s,'UTF-8','ISO-8859-1'),"\x00 ");
        if(!$meta['title'])  $meta['title'] =$c(substr($v1,3,30));
        if(!$meta['artist']) $meta['artist']=$c(substr($v1,33,30));
        if(!$meta['album'])  $meta['album'] =$c(substr($v1,63,30));
        if(!$meta['year'])   $meta['year']  =(int)trim(substr($v1,93,4),"\x00 ");
    }
    fclose($fp);
    return $meta;
}
