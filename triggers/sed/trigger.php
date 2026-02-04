<?php
// Written by: PublicWiFi
// Unless it breaks the bot, then PattyCakes wrote it
// 02/04/2026
// v0.1
// TODO: Add in ability to stack seds over multiple messages
// Notes: I definitely didn't steal the regex from another project
function sed($ircdata){
    global $ircdata;
    global $config;
    global $messageBuffer;
    
    $configfile = parse_ini_file("".$config['addons_dir']."/triggers/sed/trigger.conf");
    $sedHistoryMax = (int) $configfile['sed_history_max'];

    if ((int) $config['message_buffer_size'] < 2){
        // No point in doing anything if buffer size is 0 or 1
        // If its 1, then our sed command will be the only command to parse...

        // this likely means the buffer size wasn't set in config and it defaulted to 0
        return false;
    }

    // if the message buffer is smaller than our history max then we need to set the history max to be equal to the message buffer
    // to avoid going out of bounds
    if ((int) $config['message_buffer_size'] < $sedHistoryMax){
        $sedHistoryMax = (int) $config['message_buffer_size'];
    }

    $sedCommandStartPattern = '/^s\/.+\/.*/';
    $sedCommandSplitPattern = '/s(?:\/([^\/]+)\/([^\/]*)|:([^:]+):([^:]*))/m';

    // First see if this was an actual sed command
    if (preg_match($sedCommandStartPattern, $ircdata['fullmessage'])){

        // Break the command up into pieces
        // '/'s that are escaped with '\' are ignored for command generation purposes
        preg_match_all($sedCommandSplitPattern, $ircdata['fullmessage'], $sedCommandArray, PREG_SET_ORDER, 0);
        // $sedCommandArray should now contain all of the sed commands


        // $sedCommandArray = preg_split($sedCommandSplitPattern, $ircdata['fullmessage']);
        $sedCommandCount = count($sedCommandArray);

        // We want the first sed command in the chain to be used to find the message we want to alter
        // the subsequent commands will be used to alter that same string again
        if ($sedCommandCount == 0){
            // Since we already passed the first sed command check, we should NEVER reach this
            return false;
        }

        // We want to parse the message buffer from newest to oldest, but make sure to skip the first message because that will be the sed command that was just sent 
        // So we start $i at 1 instead of 0
        for ($i = 1; $i < $sedHistoryMax; $i++){
            // Next we check to see if the message contains the string we are wanting to replace
            $currentMessageUser = $messageBuffer[$i][0];
            $currentMessage = $messageBuffer[$i][1];
            $sedMatchPattern = "/".$sedCommandArray[0][1]."/"; // $sedCommandArray is an array of arrays. Those subarrays are made of the 3 sed parts. s, string to replace, string to replace with 
            $sedReplaceString = $sedCommandArray[0][2]; // string to replace the original string with
            
            if (!strcmp("".$currentMessageUser."", $config['nickname'])){
                // Ignore the bot's own messages
                continue;
            }
            if (preg_match($sedMatchPattern, $currentMessage) && (!preg_match($sedCommandStartPattern, $currentMessage))){
                // if the message contains the string we want to replace AND it wasn't another sed command from earlier, let's do the thing
                $newString = $currentMessage;
                $newString = preg_replace($sedMatchPattern, $sedReplaceString, $newString); // Do the first sed

                // Now if there are more seds, we process those now
                for ($i = 1; $i < $sedCommandCount; $i++){
                    $sedMatchPattern = "/".$sedCommandArray[$i][1]."/";
                    $sedReplaceString = $sedCommandArray[$i][2];

                    $newString = preg_replace($sedMatchPattern, $sedReplaceString, $newString);
                }
                sendPRIVMSG($ircdata['location'],"".$currentMessageUser." meant: ".$newString."");
                return true;
            }
        }
    }
    else{
        return false;
    }
}