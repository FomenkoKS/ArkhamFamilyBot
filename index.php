<?php
require_once("config.php");
include("Telegram.php");
// Instances the class
$telegram = new Telegram(BOT_ID);
/* If you need to manually take some parameters
*  $result = $telegram->getData();
*  $text = $result["message"] ["text"];
*  $chat_id = $result["message"] ["chat"]["id"];
*/
// Take text and chat_id from the message
$text = $telegram->Text();
$chat_id = $telegram->ChatID();
$redis=new Redis();
$message=$telegram->Message();
$arkhamGroup='@ComicsChatRu';
$admins=[32512143,265729085,165985652,74713,93155371];
$maxsize=1000;
function redis_error($error) {
    throw new error($error);
}

function getName($user){
    $user=$user['result']['user'];
    return (array_key_exists('username',$user))?$user['username']:$user['first_name'].' '.$user['last_name'];         
}

// Check if the text is a command
if(!is_null($text)){
    if(in_array($chat_id,$admins)){
        if (!$telegram->messageFromGroup()) {
            if ($text == "/newGame") {
                $redis->connect('127.0.0.1', 6379);
                $redis->set('afb_wait',1);
                $redis->close();
    
                $files = glob('files/*'); 
                foreach($files as $file){ 
                    if(is_file($file)){
                        unlink($file);
                        unlink(str_replace('files/','prepared/',$file));
                    }
                }
    
                $telegram->sendMessage([
                    'chat_id'=>$chat_id,
                    'text'=>'Пришли фотокарточки для кодировки'
                ]);
            }

        
            if ($text == "/done") {
                $redis->connect('127.0.0.1', 6379);
                $redis->set('afb_wait',0);
                $redis->close();
                $telegram->sendMessage([
                    'chat_id'=>$chat_id,
                    'text'=>'Приём фотокарточек закончен'
                ]);
            }
            
            if ($text == "/next") {
                $redis->connect('127.0.0.1', 6379);
                if($redis->lSize('agame')>0){
                    $last_photo=$redis->get('last_photo');
                    $img=$redis->lPop('agame');
                    $telegram->sendPhoto(['chat_id'=>$arkhamGroup,'photo'=>'https://axeniabot.ru/afbot/prepared/'.$img]);
                    $telegram->sendPhoto(['chat_id'=>$chat_id,'photo'=>'https://axeniabot.ru/afbot/files/'.$img]);
                }else{
                    $telegram->sendMessage([
                        'chat_id'=>$chat_id,
                        'text'=>'Фотокарточки закончились. Гаме овер, блджать!'
                    ]);
                    $telegram->sendMessage([
                        'chat_id'=>$arkhamGroup,
                        'text'=>'Игра окончена.'
                    ]);
                    $redis->set('afb_runner',0);


                    $scores=$redis->hGetAll('ArkhamScore');
                    arsort($scores);
                    $user=$telegram->getChatMember(['chat_id'=>$arkhamGroup,'user_id'=>array_keys($scores)[0]]);
                    $out=$telegram->sendMessage([
                        'chat_id'=>$chat_id,
                        'text'=>'Чемпион группы: '.getName($user)
                    ]);
                    $telegram->pinChatMessage([
                        'chat_id'=>$arkhamGroup,
                        'message_id'=>$out['result']['message_id']
                    ]);

                }
                $redis->close();
            }

            if ($text == "/startGame") {
                $files = scandir('files',1);
                if(count($files)>2){
                    $redis->connect('127.0.0.1', 6379);
                    $redis->delete('agame');
                    foreach($files as $file){
                        
                        if(is_file('files/'.$file)){
                            $redis->lPush('agame',$file);
                        }
                    }
                    $redis->set('afb_wait',0);
                    $redis->set('afb_runner',$message['from']['id']);
                    $img=$redis->lPop('agame');
                    $redis->close();

                    $telegram->sendPhoto(['chat_id'=>$arkhamGroup,'photo'=>'https://axeniabot.ru/afbot/prepared/'.$img]);
                    $telegram->sendPhoto(['chat_id'=>$chat_id,'photo'=>'https://axeniabot.ru/afbot/files/'.$img]);
                    
                }else{
                    $telegram->sendMessage([
                        'chat_id'=>$chat_id,
                        'text'=>'Папка с фотокарточками пуста. Чтобы добавить их нажмите /newGame.'
                    ]); 
                }
            }

            if ($text == "/eraseScore") {
                $redis->connect('127.0.0.1', 6379);
                $redis->delete('ArkhamScore');
                $redis->close();
                $telegram->sendMessage([
                    'chat_id'=>$chat_id,
                    'text'=>'Таблица лидеров очищена.'
                ]); 
            }

            if ($text == "/endGame") {
                $redis->connect('127.0.0.1', 6379);
                $redis->set('afb_runner',0);
                $redis->close();
            }

            if (strpos($text,"/say")!== false) {
                $telegram->sendMessage([
                    'chat_id'=>$arkhamGroup,
                    'text'=>substr($text,5)
                ]); 
            }

            if (strpos($text,"/set")!== false) {
                $a=explode(" ",$text);
                $redis->connect('127.0.0.1', 6379);
                $redis->hSet('ArkhamScore',$a[1],$a[2]);
                $telegram->sendMessage([
                    'chat_id'=>$chat_id,
                    'text'=>'Очки игрока пофиксены.'
                ]); 
                $redis->close();
            }
        }
    }else{
        
        if ($text == "/score") {
            $redis->connect('127.0.0.1', 6379);
            $scores=$redis->hGetAll('ArkhamScore');
        
            arsort($scores);
            $scores=array_slice($scores,0,5,true);
            $redis->close();
            $text="Топ игроков:\r\n";
            foreach($scores as $i=>$v){
                //$text.="$i ($v)\r\n";
                $user=$telegram->getChatMember(['chat_id'=>$arkhamGroup,'user_id'=>$i]);
                $text.=getName($user)." ($v)\r\n";
            }
            
            $telegram->sendMessage([
                'chat_id'=>$chat_id,
                'text'=>$text
            ]);
        }

        if ($telegram->messageFromGroup() && !in_array($message['from']['id'],$admins)){
            $redis->connect('127.0.0.1', 6379);
            $runner=$redis->get('afb_runner');
            if(in_array($runner,$admins)){
                $telegram->sendMessage([
                    'chat_id'=>$runner,
                    'text'=>$message['from']['first_name'].' '.$message['from']['last_name'].' ('.$message['from']['username']."):".$message['from']['id']."\r\n".$text,
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[[
                            [
                            'text'=>'+3',
                            'callback_data'=>'3|'.$message['from']['id']
                            ],
                            [
                            'text'=>'+2',
                            'callback_data'=>'2|'.$message['from']['id']
                            ],
                            [
                            'text'=>'+1',
                            'callback_data'=>'1|'.$message['from']['id']
                            ]
                        ]]
                    ])
                ]);
            }
            $redis->close();
        }
    }   
}

if(array_key_exists('photo',$message) && in_array($chat_id,$admins)){
    $redis->connect('127.0.0.1', 6379);
    $afbwait=$redis->get('afb_wait');
    $redis->close();
    if($afbwait==1){
        $file_id=end($message['photo'])['file_id'];
        $file=$telegram->getFile($file_id);
        $filepath=$file['result']['file_path'];
        $url = "https://api.telegram.org/file/bot".BOT_ID."/".$filepath;
        $filepath=explode("/",$filepath)[1];
        $photo_file = file_get_contents($url);
        $filename = 'files/' . $filepath;
        $f = fopen($filename, 'wb');
        fwrite($f, $photo_file);
        list($width, $height) = getimagesize($filename);
        $newheight=$maxsize;
        $k=$newheight/$height;
        $newwidth = ($width * $k);
        $k=0.05;
        // загрузка
        
        $new = imagecreatetruecolor($newwidth, $newheight);
        $thumb = imagecreatetruecolor(($newwidth*$k), ($newheight*$k));
        $source = imagecreatefromjpeg($filename);
        
        // изменение размера
        imagecopyresized($new, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagecopyresized($thumb, $new, 0, 0, 0, 0, ($newwidth*$k), ($newheight*$k), $newwidth, $newheight);
        imagecopyresized($new, $thumb, 0, 0, 0, 0, $newwidth, $newheight, ($newwidth*$k), ($newheight*$k));
        $pfile='prepared/'.$filepath;
        imagejpeg($new,$pfile);
        $telegram->sendPhoto(['chat_id'=>$chat_id,'photo'=>'https://axeniabot.ru/afbot/'.$pfile]);
        $telegram->sendMessage([
            'chat_id'=>$chat_id,
            'text'=>'Получил. Чтобы закончить приём фотокарточек, нажмите /done.'
        ]);

        imagedestroy($new);
        imagedestroy($thumb);
        imagedestroy($source);
    }

}

if(array_key_exists('photo',$message) && ($chat_id==-1001102623486 || in_array($chat_id,$admins))){
    $file_id = array_pop($message['photo'])['file_id'];

    $telegram->sendPhoto([
        'chat_id'=>$chat_id,
        'photo' => $file_id,
        'text'=>"Какого цвета сделать рамочку?",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[[
                [
                    'text'=>'Бела!!',
                    'callback_data'=>"p|w"
                ],[
                    'text'=>'Чорна!!',
                    'callback_data'=>"p|b"
                ]],[[
                    'text'=>'Красна!!',
                    'callback_data'=>"p|r"
                ],[
                    'text'=>'Синя!!',
                    'callback_data'=>"p|c"
                ]
            ]]
        ])
    ]);
}

if ($telegram->Callback_Query()) {
    $callback = $telegram->Callback_Query();
    $data=explode('|',$callback['data']);
    
    if($data[0]=="p"){
        $file_id=array_pop($callback['message']['photo'])['file_id'];
        $file=$telegram->getFile($file_id);
        $filepath=$file['result']['file_path'];
        $url = "https://api.telegram.org/file/bot".BOT_ID."/".$filepath;
        $filepath=explode("/",$filepath)[1];
        $photo_file = file_get_contents($url);
        $filename = 'covers/'. md5(rand(0,1000)) . $filepath;
        $f = fopen($filename, 'wb');
        fwrite($f, $photo_file);

        $source = imagecreatefromjpeg($filename);

        $width=imagesx($source);
        $height=imagesy($source);

        $newwidth=$maxsize;
        $k=$newwidth/$width;
        $newheight = ($height * $k);

        $new = imagecreatetruecolor($newwidth, $newheight);
        
        imagecopyresampled($new, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        $width=$newwidth;
        $height=$newheight;
        $source=$new;

        $logo=imagecreatefrompng("https://axeniabot.ru/afbot/logo.png");

        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        
        switch($data[1]){
            case 'b':
                $color=imagecolorallocate($source, 0, 0, 0);
                imagefilter($logo,IMG_FILTER_BRIGHTNESS,-255);
            break;
            case 'c':
                $color=imagecolorallocate($source, 14,95,143);
                imagefilter($logo,IMG_FILTER_BRIGHTNESS,-254);
                imagefilter($logo,IMG_FILTER_COLORIZE,14,95,143);
            break;
            case 'r':
                $color=imagecolorallocate($source, 216,65,58);
                imagefilter($logo,IMG_FILTER_BRIGHTNESS,-254);
                imagefilter($logo,IMG_FILTER_COLORIZE,216,65,58);
            break;
            case 'w':
                $color=imagecolorallocate($source, 255, 255, 255);
                imagefilter($logo,IMG_FILTER_BRIGHTNESS, 255);                
            break;
        }

        imagecopy($source, $logo, $width-200, $height-180, 0, 0, imagesx($logo), imagesy($logo));

        imagefilledrectangle($source, 0, 0, 20, $height, $color);
        imagefilledrectangle($source, 0, 0, $width, 20, $color);
        imagefilledrectangle($source, $width-20, 0, $width, $height, $color);
        imagefilledrectangle($source, 0, $height-20, $width, $height, $color);

        imagejpeg($source,$filename);
        
        $telegram->editMessageMedia([
            'chat_id'=>$callback['message']['chat']['id'],
            'message_id'=>$callback['message']['message_id'],
            'media'=>json_encode(['type'=>'photo','media'=>'https://axeniabot.ru/afbot/'.$filename]),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[[
                    [
                        'text'=>'Бела!!',
                        'callback_data'=>"p|w"
                    ],[
                        'text'=>'Чорна!!',
                        'callback_data'=>"p|b"
                    ]],[[
                        'text'=>'Красна!!',
                        'callback_data'=>"p|r"
                    ],[
                        'text'=>'Синя!!',
                        'callback_data'=>"p|c"
                    ]
                ]]
            ])
            ]
        );
        
        imagedestroy($source);
        imagedestroy($white);
        imagedestroy($logo);
        imagedestroy($new);

        fclose($filename);
        unlink($filename);
    }else{
        if ($callback['message']['from']['id']==explode(':',BOT_ID)[0]){
            $redis->connect('127.0.0.1', 6379);
            if($redis->get('afb_runner')>0){
                
                $allScore=$redis->hIncrBy('ArkhamScore', $data[1], $data[0]);
                
                $user=getName($telegram->getChatMember(['chat_id'=>$arkhamGroup,'user_id'=>$data[1]]));
            
                $score=($data[0]=='1')?'1 очко':$data[0].' очка';
                $telegram->sendMessage(['chat_id'=>$arkhamGroup,'text'=>"Игрок $user получает $score. Общий счёт: $allScore"]);
                $telegram->deleteMessage(['chat_id'=>$callback['message']['chat']['id'],'message_id'=>$callback['message']['message_id']]);
            }else{
                $telegram->sendMessage(['chat_id'=>$callback['message']['chat']['id'],'text'=>'Игра окончена']);
            }
            $afbwait=$redis->get('afb_wait');
            $redis->close();
        }
    }
    
}
