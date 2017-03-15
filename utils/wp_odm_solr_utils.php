<?php

  use Analog\Analog;
  use Analog\Handler;

  define("WP_ODM_SOLR_DEFAULT_LOG_PATH","/tmp/wp_odm_solr.log");

  function wp_odm_solr_highlight_search_words($search_query,$to_highlight) {

    $splitted_words = explode(" ",$search_query);

    $highlighted = $to_highlight;
    foreach ($splitted_words as $word):
      if (!empty($word) && strpos($to_highlight,$word) !== FALSE):
        $highlighted = str_replace($word,"<b>" . $word . "</b>",$highlighted);
      endif;
    endforeach;

    return $highlighted;
  }

  function wp_odm_solr_log($text) {

    if (!$GLOBALS['wp_odm_solr_options']->get_option('wpckan_setting_log_enabled')) return;

    $bt = debug_backtrace();
    $caller = array_shift($bt);

    if (!empty($GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_log_path')))
      Analog::handler(Handler\File::init ($GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_log_path')));
    else
      Analog::handler(Handler\File::init (WP_ODM_SOLR_DEFAULT_LOG_PATH));

    Analog::log ( "[ " . $caller['file'] . " | " . $caller['line'] . " ] " . $text );
  }

?>
