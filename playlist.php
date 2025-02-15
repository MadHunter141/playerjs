<?php
header('Content-Type: text/html; charset=utf-8');

function alloha_api($url) {
	if ( $curl = curl_init())
	{
		$headers = array(
        	'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.2924.87 Safari/537.36',
        	'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        	'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
        	'Connection: keep-alive',
        	'Cache-Control: max-age=0',
        	'Upgrade-Insecure-Requests: 1'
    	);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$out = curl_exec($curl);
		$parse = json_decode($out, true);
		curl_close($curl);
	}
	else {
		$out = file_get_contents($url);
		$parse = json_decode($out, true);
	}
	return $parse;
}

$config_mod = unserialize( file_get_contents( ENGINE_DIR . '/data/alloha.config' ) );

if (isset($_POST['kinopoisk_id'])) $kinopoisk_id = $_POST['kinopoisk_id'];
else $kinopoisk_id = false;
if (isset($_POST['imdb_id'])) $imdb_id = $_POST['imdb_id'];
else $imdb_id = false;
if (isset($_POST['world_art_id'])) $world_art_id = $_POST['world_art_id'];
else $world_art_id = false;
if (isset($_POST['tmdb_id'])) $tmdb_id = $_POST['tmdb_id'];
else $tmdb_id = false;
if (isset($_POST['token_movie'])) $token_movie = $_POST['token_movie'];
else $token_movie = false;
if (isset($_POST['last_translator'])) $last_translator = $_POST['last_translator'];
else $last_translator = false;
if (isset($_POST['last_season'])) $last_season = $_POST['last_season'];
else $last_season = false;
if (isset($_POST['last_episode'])) $last_episode = $_POST['last_episode'];
else $last_episode = false;
if (isset($_POST['action'])) $action = $_POST['action'];
else $action = 'load_player';
if (isset($_POST['my_season'])) $my_season = $_POST['my_season'];
else $my_season = false;
if (isset($_POST['cook'])) $cook = $_POST['cook'];
else $cook = 0;
if (isset($_POST['news_id'])) $news_id = $_POST['news_id'];
else $news_id = false;
if ( isset( $_POST['with_seasons'] ) ) $with_seasons = $_POST['with_seasons'];
else $with_seasons = 0;
if ( isset( $_POST['api_token'] ) ) $api_token = $_POST['api_token'];
else $api_token = $config_mod['api_token'];
if (isset($_POST['this_season'])) $this_season = $_POST['this_season'];
else $this_season = 0;
if (isset($_POST['this_episode'])) $this_episode = $_POST['this_episode'];
else $this_episode = 0;
if (isset($_POST['this_translator'])) $this_translator = $_POST['this_translator'];
else $this_translator = 0;
    
$playlist = dle_cache('alloha_playlist', $news_id, false);
    
if ( $playlist !== FALSE ) $playlist = json_decode($playlist, true);
elseif ( $kinopoisk_id || $imdb_id || $tmdb_id || $world_art_id ) {

	if ($imdb_id) {
		$alloha = alloha_api('https://api.apbugall.org/?token='.$api_token.'&imdb='.$imdb_id);
	}

	elseif ($tmdb_id) {
		$alloha = alloha_api('https://api.apbugall.org/?token='.$api_token.'&tmdb='.$tmdb_id);
	}

	elseif ($world_art_id) {
		$alloha = alloha_api('https://api.apbugall.org/?token='.$api_token.'&world_art='.$world_art_id);
	}
  
	elseif ($token_movie) {
		$alloha = alloha_api('https://api.apbugall.org/?token='.$api_token.'&token_movie='.$token_movie);
	}  

	else {
		$alloha = alloha_api('https://api.apbugall.org/?token='.$api_token.'&kp='.$kinopoisk_id);
	}
// print_r($alloha);die();
    if ( $alloha['status'] == 'success' ) {

    	$seasons = [];

    	foreach ($alloha['data']['seasons'] as $season => $episode) {
    		foreach ($episode['episodes'] as $ep_num => $episode_info) {
    			foreach ($episode_info['translation'] as $translator_id => $translation) {
    				$seasons[$translator_id]['translator_name'] = $translation['translation'];
    				$seasons[$translator_id]['seasons'][$season][] = $ep_num;
    				sort($seasons[$translator_id]['seasons'][$season]);
    				ksort($seasons[$translator_id]['seasons']);
    			}
    			
    		}
    	}
    	
    	// print_r($seasons);die();

    	$playlist = array();
    	$max_episodes = array();
    	$max_seasons = array();
    	foreach ($seasons as $num => $translators) {
    	    $playlist[$num]['translator_name'] = $translators['translator_name'];
    	    $playlist[$num]['translator_id'] = $num;
    	    $playlist[$num]['translator_link'] = 'https://polygamist.as.alloeclub.com/?token_movie='.$alloha['data']['token_movie'].'&token='.$config_mod['api_token'];
    	    $max_season = 0;
    	    foreach ( $translators['seasons'] as $season => $episode ) {
    	        foreach ( $episode as $ep_num ) {
    	            $playlist[$num]['episodes'][$season][] = $ep_num;
    	            $max_episode = $ep_num;
    	        }
    	        $max_season = $season > $max_season ? $season : $max_season;
    	    }
    	    $playlist[$num]['max_season'] = $max_season;
    	    $playlist[$num]['max_episode'] = $max_episode;
    	    $max_seasons[] = $max_season;
    	    $max_episodes[] = $max_episode;
    	}
    	//print_r($playlist);die();
    	array_multisort($max_seasons, SORT_DESC, $max_episodes, SORT_DESC, $playlist);
    	$playlist[0]['serial_name'] = $alloha['data']['name'];
    	create_cache('alloha_playlist', json_encode($playlist, JSON_UNESCAPED_UNICODE), $news_id, false);
        unset($translators);
        unset($episode);
        unset($alloha);
        unset($max_episodes);
        unset($max_seasons);
        unset($max_episode);
        unset($max_season);
    }
}

$serial_name = $playlist[0]['serial_name'];

if ($playlist && $action == 'load_player') {
    
    if ($playlist[0]['translator_name']) {
        $translator_num = 0;
        foreach ($playlist as $num => $translation) {
            if ( $last_translator > 0 ) {
                if ( $last_translator == $translation['translator_id'] ) {
                    $active_tr = " active";
                    $translator_num = $num;
                    $translator_title = $translation['translator_name'];
                }
                else $active_tr = "";
            }
            else {
                if ( $num == 0 ) $active_tr = " active";
                else $active_tr = "";
            }
            $translators .= '<li onclick="translates();" class="b-translator__item'.$active_tr.'" data-this_translator="'.$translation['translator_id'].'">'.$translation['translator_name'].'</li>';
        }
        $translators = '<div class="b-translators__block"><div class="b-translators__title">В русской озвучке от:</div><ul id="translators-list" class="b-translators__list">'.$translators.'</ul></div>';
    }
    if ($last_season > 0 && $last_translator > 0 && $last_episode > 0 && $cook > 0 && $with_seasons > 0) {
        $lastepisodeout = '<div class="b-post__lastepisodeout"><h2><i class="fa fa-eye" style="font-size: 20px !important;"></i>  ' . $serial_name . '<span id="les">. Вы остановились на ' . $last_season . ' сезоне ' . $last_episode . ' серии в озвучке «' . $translator_title . '»</span><i class="fa fa-trash" onclick="del();" id="lesc" title="Удалить отметку"></i></h2> </div>';
    }
    elseif ($last_season > 0 && $last_translator > 0 && $last_episode > 0 && $cook > 0 && $with_seasons == 0) {
        $lastepisodeout = '<div class="b-post__lastepisodeout"><h2><i class="fa fa-eye" style="font-size: 20px !important;"></i>  ' . $serial_name . '<span id="les">. Вы остановились на ' . $last_episode . ' серии в озвучке «' . $translator_title . '»</span><i class="fa fa-trash" onclick="del();" id="lesc" title="Удалить отметку"></i></h2> </div>';
    }
    else {
        $lastepisodeout = '<div class="b-post__lastepisodeout"><h2><i class="fa fa-eye" style="font-size: 20px !important;"></i>  ' . $serial_name . '<span id="ln"> ' . $playlist[$translator_num]['max_season'] . ' сезон ' . $playlist[$translator_num]['max_episode'] . ' серия</span><span id="les"></span></h2></div>';
    }
    $ajax_player = '<div id="player" class="b-player" style="text-align: center;">';
    $seasons = '<ul id="simple-seasons-tabs" class="b-simple_seasons__list clearfix">';

    foreach ($playlist[$translator_num]['episodes'] as $season => $episode) {
            
        if ( $last_season > 0 ) {
            if ( $last_season == $season ) $active_szn = " active";
            else $active_szn = "";
        }
        else {
            if ( $playlist[$translator_num]['max_season'] == $season ) $active_szn = " active";
            else $active_szn = "";
        }
                
        $seasons .= '<li onclick="seasons();" class="b-simple_season__item' . $active_szn . '" data-this_season="' . $season . '" data-this_translator="' . $playlist[$translator_num]['translator_id'] . '">Сезон ' . $season . '</li>';
    }
    $seasons .= '</ul>';
    
    $episodes = '<div class="prenext"><div class="prevpl" onclick="prevpl();">&lsaquo;</div><div id="simple-episodes-tabs">';
    $episodes .= '<ul id="simple-episodes-list" class="b-simple_episodes__list clearfix">';
    
    if ( $last_season > 0 ) $season_num = $last_season;
    else $season_num = $playlist[$translator_num]['max_season'];
            
    foreach ($playlist[$translator_num]['episodes'][$season_num] as $episode_num) {
        if ( $last_episode > 0 ) {
            if ( $last_episode == $episode_num ) $active_epzd = " active";
            else $active_epzd = "";
        }
        else {
            if ( $playlist[$translator_num]['max_episode'] == $episode_num ) $active_epzd = " active";
            else $active_epzd = "";
        }
        
        $episodes .= '<li onclick="episodes();" class="b-simple_episode__item' . $active_epzd . '" data-this_season="' . $season_num . '" data-this_episode="' . $episode_num . '" data-this_translator="' . $playlist[$translator_num]['translator_id'] . '">Серия ' . $episode_num . '</li>';
    }
    $episodes .= '</ul>';
    $episodes .= '</div><div class="nextpl" onclick="nextpl();">&rsaquo;</div></div>';
        
    if ( $last_season > 0 ) $this_season = "&season=".$last_season;
    else $this_season = "&season=".$playlist[$translator_num]['max_season'];
    if ( $last_episode > 0 ) $this_episode = "&episode=".$last_episode;
    else $this_episode = "&episode=".$playlist[$translator_num]['max_episode'];
    if ( $last_translator > 0 ) $this_translator = "&translation=".$last_translator;
    else $this_translator = "&translation=".$playlist[$translator_num]['translator_id'];
        
    $iframe_url = $playlist[$translator_num]['translator_link'].'&hidden=season,episode,translation';
    $iframe .= '<div id="ibox"><div id="player-loader-overlay"></div><div id="player_alloha" style="height: 100%; margin: 0 auto; width: 100%;"><iframe src="'.$iframe_url.$this_season.$this_episode.$this_translator.'" width="724" height="460" frameborder="0" allowfullscreen=""></iframe></div>';

    if ( $with_seasons > 0 )
        $ajax_player = $ajax_player . $seasons . $iframe . $episodes;
    else
        $ajax_player = $ajax_player . $iframe . $episodes;

    $ajax_player .= '</div></div>';
    $ajax_player = $lastepisodeout . $translators . $ajax_player;

    echo $ajax_player;
    
}

if ($playlist && $action == 'translates') {

    foreach ( $playlist as $pl ) {
        if ( $pl['translator_id'] == $this_translator ) {
            $this_playlist = $pl;
            break;
        }
    }

    $seasons = '<ul id="simple-seasons-tabs" class="b-simple_seasons__list clearfix">';
    foreach ($this_playlist['episodes'] as $season => $episode) {
            
        if ( $this_playlist['max_season'] == $season ) $active_szn = " active";
        else $active_szn = "";
                
        $seasons .= '<li onclick="seasons();" class="b-simple_season__item' . $active_szn . '" data-this_season="' . $season . '" data-this_translator="' . $this_translator . '">Сезон ' . $season . '</li>';
            
        if ( $this_playlist['max_season'] == $season ) {
            
            $episodes = '<div class="prenext"><div class="prevpl" onclick="prevpl();">&lsaquo;</div><div id="simple-episodes-tabs">';
            $episodes .= '<ul id="simple-episodes-list" class="b-simple_episodes__list clearfix">';
            
            foreach ($episode as $episode_num) {
                if ( $this_playlist['max_episode'] == $episode_num ) $active_epzd = " active";
                else $active_epzd = "";
            
                $episodes .= '<li onclick="episodes();" class="b-simple_episode__item' . $active_epzd . '" data-this_season="' . $season . '" data-this_episode="' . $episode_num . '" data-this_translator="' . $this_translator . '">Серия ' . $episode_num . '</li>';
            }
            $episodes .= '</ul>';
            $episodes .= '</div><div class="nextpl" onclick="nextpl();">&rsaquo;</div></div>';
        }
    }
    $seasons .= '</ul>';
        
    if ( $last_season > 0 ) $this_season = "&season=".$last_season;
    else $this_season = "&season=".$this_playlist['max_season'];
    if ( $last_episode > 0 ) $this_episode = "&episode=".$last_episode;
    else $this_episode = "&episode=".$this_playlist['max_episode'];
    if ( $last_translator > 0 ) $this_translator = "&translation=".$last_translator;
    else $this_translator = "&translation=".$this_translator;
        
    $iframe_url = $this_playlist['translator_link'].'&hidden=season,episode,translation';
    $iframe .= '<div id="ibox"><div id="player-loader-overlay"></div><div id="player_alloha" style="height: 100%; margin: 0 auto; width: 100%;"><iframe src="'.$iframe_url.$this_season.$this_translator.$this_episode.'" width="724" height="460" frameborder="0" allowfullscreen=""></iframe></div>';

    if ( $with_seasons > 0 )
        $ajax_player = $seasons . $iframe . $episodes;
    else
        $ajax_player = $iframe . $episodes;

    echo $ajax_player;
}

if ($playlist && $action == 'seasons') {
    
    foreach ( $playlist as $pl ) {
        if ( $pl['translator_id'] == $this_translator ) {
            $this_playlist = $pl;
            break;
        }
    }
            
    $episodes = '<div class="prenext"><div class="prevpl" onclick="prevpl();">&lsaquo;</div><div id="simple-episodes-tabs">';
    $episodes .= '<ul id="simple-episodes-list" class="b-simple_episodes__list clearfix">';
            
    foreach ($this_playlist['episodes'][$this_season] as $episode_num) {
        if ( min($this_playlist['episodes'][$this_season]) == $episode_num ) {
          $active_epzd = " active";
          $min_episode = $episode_num;
        }
        else $active_epzd = "";
            
        $episodes .= '<li onclick="episodes();" class="b-simple_episode__item' . $active_epzd . '" data-this_season="' . $this_season . '" data-this_episode="' . $episode_num . '" data-this_translator="' . $this_translator . '">Серия ' . $episode_num . '</li>';
    }
    $episodes .= '</ul>';
    $episodes .= '</div><div class="nextpl" onclick="nextpl();">&rsaquo;</div></div>';
        
    $this_season = "&season=".$this_season;
    $this_episode = "&episode=".$min_episode;
    $this_translator = "&translation=".$this_translator;
        
    $iframe_url = $this_playlist['translator_link'].'&hidden=season,episode,translation';
    $iframe .= '<div id="ibox"><div id="player-loader-overlay"></div><div id="player_alloha" style="height: 100%; margin: 0 auto; width: 100%;"><iframe src="'.$iframe_url.$this_season.$this_translator.$this_episode.'" width="724" height="460" frameborder="0" allowfullscreen=""></iframe></div>';

    $ajax_player = $iframe . $episodes;

    echo $ajax_player;
    
}
