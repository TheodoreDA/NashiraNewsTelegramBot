<?php
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    require_once 'madeline.php';
    require_once 'envVars.php';
    require_once 'Logger.php';
    require_once 'TwitterOAuth/autoload.php';
    require_once 'EmojiDetection/Emoji.php';

    use danog\MadelineProto\API;
    use danog\MadelineProto\Settings\AppInfo;
    use Abraham\TwitterOAuth\TwitterOAuth;

    $envVar = new EnvVars(__DIR__.'/.env');
    $logger = new Logger(__DIR__.'/LogTwitterBot.txt');


    $logger->print("Welcome to our very custom NashiraNews's TwitterBot!");
    // Getting the Telegram API id and hash from the .env file
    $TelegramApiId = $envVar->get_env_var('TELEGRAM_API_ID');
    $TelegramApiHash = $envVar->get_env_var('TELEGRAM_API_HASH');
    // If one of them is not found, exit
    if ($TelegramApiId == null || $TelegramApiHash == null) {
        $logger->print("Could not find telegram app's secrets in '.env' file. Exiting.");
        return;
    }
    // Setting up the Telegram object
    $logger->print('Logging in to Telegram...');
    $settings = (new AppInfo)
        ->setApiId((int) $TelegramApiId)
        ->setApiHash($TelegramApiHash);
    $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);

    // Getting the id of the last telegram post processed from the .env file
    $logger->print("Getting last post's id that has been processed...");
    $lastPostId = $envVar->get_env_var('LAST_POST_ID');
    // Setting the max number of telegram post being received at the same time
    $limit = 5;
    // If no id found, set limit to one
    if ($lastPostId == null) {
        $logger->print("None found.");
        $lastPostId = 0;
        $limit = 1;
    }
    $lastPostId = (int) $lastPostId;

    // Getting the telegram url of the channel NashiraNews from the .env file
    $TelegramChannelUrl = $envVar->get_env_var('TELEGRAM_CHANNEL_URL');
    if ($TelegramChannelUrl == null) {
        $logger->print("Could not find the channel url to get posts from. Exiting.");
        return;
    }
    // Getting the Telegram posts
    $logger->print("Getting last posts...");
    $params = ['peer' => $TelegramChannelUrl, 'limit' => $limit, 'min_id' => $lastPostId,
        'offset_id' => 0, 'offset_date' => 0, 'add_offset' => 0, 'max_id' => 0, 'hash' => 0];
    $lastPosts = $MadelineProto->messages->getHistory($params)['messages'];
    try {
        $lastPosts = $MadelineProto->messages->getHistory($params)['messages'];
    } catch (Exception $exception) {
        $strException = (string) $exception;
        switch ($exception->getCode()) {
            case (401):
                $logger->print('The Telegram authorization key has expired. Exiting.');
                return (84);
            default:
                $reason = explode(", caused by", $strException)[0];
                $logger->print('Unknown Exception: '.$reason);
                exit(84);
        }
    }
    // If no post received, exit
    if (count($lastPosts) == 0) {
        $logger->print('There is no new post. Exiting.');
        return;
    }
    // If there were no post saved, saving the last one, then exit
    if ($lastPostId == 0) {
        $logger->print("As no post id was saved, just saving the last post's id to not flood Twitter. Exiting.");
        $envVar->set_env_var('LAST_POST_ID', $lastPosts[0]['id']);
        return;
    }

    $toRemove = array();
    foreach ($lastPosts as $i => $post) {
        // For each received post, get all the emojis of the post's message
        $emojis = Emoji\detect_emoji($post['message']);

        foreach ($emojis as $emoji) {
            // For each emoji found, looking for the "studio_microphone" emoji: 🎙
            // Then store it's key (id, position) to delete the corresponding post later
            // We can add conditions to add more emojis to exclude
            if ($emoji["short_name"] == "studio_microphone")
                $toRemove[] = $i;
        }
    }
    // Delete the posts with the keys stored
    foreach ($toRemove as $e)
        unset($lastPosts[$e]);

    if (count($lastPosts) == 1)
        $logger->print('Found 1 new post!');
    else
        $logger->print('Found '.count($lastPosts).' new posts!');

    // Getting the Twitter API key, API key secret, API token and API token secret from the .env file
    $logger->print('Logging in to Twitter...');
    $TwitterApiKey = $envVar->get_env_var('TWITTER_API_KEY');
    $TwitterApiKeySecret = $envVar->get_env_var('TWITTER_API_KEY_SECRET');
    $TwitterApiToken = $envVar->get_env_var('TWITTER_API_TOKEN');
    $TwitterApiTokenSecret = $envVar->get_env_var('TWITTER_API_TOKEN_SECRET');
    // If one of them is not found, exit
    if ($TwitterApiKey == null || $TwitterApiKeySecret == null ||
        $TwitterApiToken == null || $TwitterApiTokenSecret == null) {
        $logger->print("Could not find twitter app's secrets in '.env' file. Exiting.");
        return;
    }

    // Setting up the Twitter object
    $twitterConnection = new TwitterOAuth($TwitterApiKey, $TwitterApiKeySecret, 
                                   $TwitterApiToken, $TwitterApiTokenSecret);
    // Setting the version of the Twitter API to 2 (newest)
    $twitterConnection->setApiVersion('2');
    // Check if the secrets from the .env file can make a generic request. If not, exit
    $poke = $twitterConnection->get('users', ['ids' => 12]);
    if ($twitterConnection->getLastHttpCode() == 200) {
        $logger->print('Connected to Twitter successfully!');
    } else {
        $logger->print("Cannot connect to Twitter via the tokens given. Exiting.");
        var_dump($poke);
        return;
    }

    // For each post received :
    foreach ($lastPosts as $i => $post) {
        // Create a link to the telegram post with channel url and id's post
        $telegramPostLink = $TelegramChannelUrl.'/'.$post['id'];
        // Custom message that will be displayed just before the post's link
        $telegramPostMsgLink = "";
        // Tweets max length
        $tweetMaxLength = 280;
        // Calculating the length taken by two line break, the length of the link's message, and the link's length to substract to the max tweet length
        $messageMaxLength = $tweetMaxLength - 2 - strlen($telegramPostMsgLink) - strlen($telegramPostLink);
        $tweet = $post['message'];

        // If there is a line break in the post, cut the message at the first line break
        if (strpos($post['message'], "\n") == TRUE)
            $tweet = explode("\n", $tweet, 2)[0];
        // Cut the tweet message to keep enough space to add the link
        $tweet = substr($tweet, 0, $messageMaxLength);
        // Add two line breaks, the link's message and the link
        $tweet .= PHP_EOL.PHP_EOL.$telegramPostMsgLink.$telegramPostLink;

        if ($i != 0)
            $logger->print('');
        // Send tweet
        $logger->print('Sending a tweet...');
        $tweetReponse = $twitterConnection->post("tweets", ["text" => $tweet], true);
        
        if ($twitterConnection->getLastHttpCode() == 200) {
            $logger->print('Sent!');
        } else {
            // If the tweet somehow didn't succeed, print the error in the log
            $logger->print("Tweet wasn't posted. Something went wrong:");
            $logger->print($tweetReponse->status." ".$tweetReponse->title.": ".$tweetReponse->detail);
        }
        // Store the post's id to the .env file
        $envVar->set_env_var('LAST_POST_ID', $lastPosts[$i]['id']);
    }
?>
