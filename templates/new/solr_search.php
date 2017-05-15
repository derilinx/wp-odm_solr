<?php get_header(); ?>

<?php
  include_once dirname(dirname(plugin_dir_path(__FILE__))).'/utils/solr-wp-manager.php';
  include_once dirname(dirname(plugin_dir_path(__FILE__))).'/utils/solr-ckan-manager.php';

  $param_query = !empty($_GET['s']) ? $_GET['s'] : null;
  $param_type = isset($_GET['type']) ? $_GET['type'] : null;
  $param_license = isset($_GET['license']) ? $_GET['license'] : array();
  $param_taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : 'all';
  $param_language = isset($_GET['language']) ? $_GET['language'] : array();
  $param_page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
  $param_page_solr = (isset($_GET['page']) && (int)$_GET['page'] > 0) ? ((int)$_GET['page'] -1) : 0;
  $param_country = odm_country_manager()->get_current_country() == 'mekong' && isset($_GET['country']) ? $_GET['country'] : array();
  if (empty($param_country) && odm_country_manager()->get_current_country() != 'mekong'):
    $param_country = array(odm_country_manager()->get_current_country_code());
  endif;
	$param_sorting = isset($_GET['sorting']) ? $_GET['sorting'] : 'score';

  //================ Filter Values ===================== //

  $taxonomy_list = odm_taxonomy_manager()->get_taxonomy_list();
  $country_codes_iso2 = odm_country_manager()->get_country_codes_iso2_list();
  $languages = odm_language_manager()->get_supported_languages();
  $license_list = wpckan_get_license_list();
  $top_tier_taxonomic_terms = odm_taxonomy_manager()->get_taxonomy_top_tier();

  //================ Build query attributes ===================== //

  $attrs = [];
  $control_attrs = array(
    "sorting" => $param_sorting,
    "limit" => 15
  );

  if ($param_page_solr) {
    $control_attrs['page'] = $param_page_solr;
  }

  //================ Search types ===================== //

  $supported_search_types = array(
    'dataset' => array(
      'title' => 'Datasets',
      'icon' => 'fa fa-database',
      'type' => 'ckan',
      'archive_url' => '/data'
    ),
    'library_record' => array(
      'title' =>'Library publications',
      'icon' => 'fa fa-book',
      'type' => 'ckan',
      'archive_url' => null
    ),
    'laws_record' => array(
      'title' =>'Laws',
      'icon' => 'fa fa-gavel',
      'type' => 'ckan',
      'archive_url' => null
    ),
    'agreement' => array(
      'title' =>'Agreements',
      'icon' => 'fa fa-handshake-o',
      'type' => 'ckan',
      'archive_url' => null
    ),
    'map-layer' => array(
      'title' => 'Maps',
      'icon' => 'fa fa-map-marker',
      'type' => 'wp',
      'archive_url' => '/layers'
    ),
    'news-article' => array(
      'title' => 'News articles',
      'icon' => 'fa fa-quote-left',
      'type' => 'wp',
      'archive_url' => '/news'
    ),
    'topic' => array(
      'title' => 'Topics',
      'icon' => 'fa fa-list',
      'type' => 'wp',
      'archive_url' => '/topics'
    ),
    'profiles' => array(
      'title' => 'Profiles',
      'icon' => 'fa fa-briefcase',
      'type' => 'wp',
      'archive_url' => '/profiles'
    ),
    'story' => array(
      'title' => 'Stories',
      'icon' => 'fa fa-lightbulb-o',
      'type' => 'wp',
      'archive_url' => '/story'
    ),
    'announcement' => array(
      'title' => 'Announcements',
      'icon' => 'fa fa-bullhorn',
      'type' => 'wp',
      'archive_url' => '/announcements'
    ),
    'site-update' => array(
      'title' => 'Site updates',
      'icon' => 'fa fa-flag',
      'type' => 'wp',
      'archive_url' => '/updates'
    )
  );

  //================ Run queries and gather both results and facets ===================== //

  $results = [];
  $facets = [];

  $facets_mapping = array(
    "categories" => "vocab_taxonomy",
    "odm_spatial_range" => "extras_odm_spatial_range",
    "odm_language" => "extras_odm_language",
    "tags" => "extras_odm_keywords",
    "vocab_taxonomy" => "vocab_taxonomy",
    "extras_odm_spatial_range" => "extras_odm_spatial_range",
    "extras_odm_language" => "extras_odm_language",
    "extras_odm_keywords" => "extras_odm_keywords",
    "license_id" => "license_id"
  );

  // -------------- Get all results --------------- //
  foreach ($supported_search_types as $key => $value):
    $attrs = [];
    $result = null;

    if ($value['type'] == 'ckan'):
      //Taxonomy
      if (isset($param_taxonomy) && $param_taxonomy != 'all') {
        $attrs["vocab_taxonomy"] = $param_taxonomy;
      }

      // Language
      if (!empty($param_language)) {
        $attrs["extras_odm_language"] = $param_language;
      }

      // Country
      if (!empty($param_country) && $param_country != 'mekong') {
        $attrs["extras_odm_spatial_range"] = $param_country;
      }

      //License
      if (!empty($param_license)) {
        $attrs['license_id'] = $param_license;
      }

      $attrs["dataset_type"] = $key;
      $attrs["capacity"] = "public";
      $result = WP_Odm_Solr_CKAN_Manager()->query($param_query,$attrs,$control_attrs);
    else:

      //Taxonomy
      if (isset($param_taxonomy) && $param_taxonomy != 'all') {
        $attrs["categories"] = $param_taxonomy;
      }

      // Language
      if (!empty($param_language)) {
        $attrs["odm_language"] = $param_language;
      }

      // Country
      if (!empty($param_country) && $param_country != 'mekong') {
        $attrs["odm_spatial_range"] = $param_country;
      }

      $attrs["type"] = $key;
      $result = WP_Odm_Solr_WP_Manager()->query($param_query,$attrs,$control_attrs);
    endif;
    
    $results[$key] = $result["resultset"];
    $facets[$key] = $result["facets"];
  endforeach; ?>

<section class="container">

  <?php
    if (!WP_Odm_Solr_WP_Manager()->ping_server() || !WP_Odm_Solr_CKAN_Manager()->ping_server()):  ?>
      <div class="row">
        <div class="sixteen columns">
            <p class="error">
              <?php _e("wp-odm_solr plugin is not properly configured. Please contact the system's administrator","wp-odm_solr"); ?>
            </p>
        </div>
      </div>
  <?php
    else:
      
      // -------------- Define top param type --------------- //
      if (!isset($param_type)):
        foreach ($supported_search_types as $key => $value):
          if (isset($results[$key]) && $results[$key]->getNumFound() > 0):
            $param_type = $key;
            break;
          endif;
        endforeach;
      endif;
      
      // -------------- Define facets --------------- //
      foreach ($facets[$param_type] as $facet_key => $facet):
        $facet_key_mapped = $facets_mapping[$facet_key];
        if (!isset($facets[$param_type][$facet_key_mapped])):
          $facets[$param_type][$facet_key_mapped] = [];
        endif;
        foreach ($facet as $facet_value => $count):
          if ($facet_key_mapped == "vocab_taxonomy"):
            foreach ($top_tier_taxonomic_terms as $top_tier_term => $children):
              if (in_array($facet_value,$children) || $facet_value == $top_tier_term):
                $facet_value = $top_tier_term;
                break;
              endif;
            endforeach;
          endif;
          $facets[$param_type][$facet_key_mapped][$facet_value] = $count;
        endforeach;
      endforeach; ?>
      
  		<div class="row">
        <div class="four columns">
          <div class="result_links">
          <h4><?php _e('Search Results','wp-odm_solr'); ?> for "<?php _e($param_query,'wp-odm_solr'); ?>"</h4>
          <?php
            foreach ($supported_search_types as $key => $value):
              $count = ($results[$key]) ? $results[$key]->getNumFound() : 0;
              if ($count > 0): ?>

              <div class="result_link_list <?php if ($param_type == $key) echo "data-number-results-medium" ?>">
                <a href="<?php echo construct_url($_SERVER['REQUEST_URI'], 'type', $key); ?>">
                  <i class="<?php echo $value['icon']; ?>"></i>
                  <?php echo __($value['title'],'wp-odm_solr') . " (".$count.")"; ?>
                </a>
              </div>

          <?php
              endif;
            endforeach
          ?>
          </div>
          <div class="data-advanced-filters">
            <form>
            <?php include plugin_dir_path(__FILE__). 'partials/filters.php'; ?>
          </div>
          
          <?php
            if (isset($param_type) && isset($supported_search_types[$param_type])):
              $supported_type = $supported_search_types[$param_type];
              if (isset($supported_type['archive_url'])): ?>
                <div class="result_links hideOnMobile">
                  <a href="<?php echo $supported_type['archive_url'] ?>"><h4><?php _e("Explore more","wp-odm_solr") ?> <?php _e($supported_type['title'],"wp-odm_solr") ?></h4></a>
                </div>
          <?php
              endif;
            endif;
           ?>
    		</div>
        <!-- ============== Search input ============= -->
  			<div class="twelve columns solr_results search-results">
          <input id="search_field" name="s" type="text" class="full-width-search-box search_field" value="<?php echo $_GET["s"]?>" placeholder="<?php _e("Search datasets, topics, news articles...","wp-odm_solr"); ?>" data-solr-host="<?php echo $GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_solr_host'); ?>" data-solr-scheme="<?php echo $GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_solr_scheme'); ?>" data-solr-path="<?php echo $GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_solr_path'); ?>" data-solr-core-wp="<?php echo $GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_solr_core_wp'); ?>" data-solr-core-ckan="<?php echo $GLOBALS['wp_odm_solr_options']->get_option('wp_odm_solr_setting_solr_core_ckan'); ?>"></input>
          </form>
          
          <?php                                     
          $content_resultset = $results[$param_type];
          $content_resultcount = ($content_resultset) ? $content_resultset->getNumFound() : 0;
          ?>
            <h4>
            <?php echo $content_resultcount . ' '
                        . $supported_search_types[$param_type]["title"]
                        . __(' found for','wp-odm_solr') . '"' . $param_query. '"'; ?>
            </h4>

            <?php

            if (isset($content_resultset) && $content_resultcount > 0):
              foreach ($content_resultset as $document): ?>

                <?php
                if($supported_search_types[$param_type]['type'] == 'ckan'):
                  include plugin_dir_path(__FILE__). 'partials/ckan_result_template.php';
                else:
                  if ($param_type == 'map-layer'):
                    include plugin_dir_path(__FILE__). 'partials/wp_map_layer_result_template.php';
                  elseif ($param_type == 'news-article'):
                    //include plugin_dir_path(__FILE__). 'partials/wp_news_article_result_template.php';
                    include plugin_dir_path(__FILE__). 'partials/wp_result_template.php';
                  elseif ($param_type == 'topic'):
                    include plugin_dir_path(__FILE__). 'partials/wp_topic_result_template.php';
                  elseif ($param_type == 'profiles'):
                    include plugin_dir_path(__FILE__). 'partials/wp_profiles_result_template.php';
                  elseif ($param_type == 'story'):
                    include plugin_dir_path(__FILE__). 'partials/wp_story_result_template.php';
                  elseif ($param_type == 'announcement'):
                    //include plugin_dir_path(__FILE__). 'partials/wp_announcement_result_template.php';
                    include plugin_dir_path(__FILE__). 'partials/wp_result_template.php';
                  elseif ($param_type == 'site-update'):
                    //include plugin_dir_path(__FILE__). 'partials/wp_site_update_result_template.php';
                    include plugin_dir_path(__FILE__). 'partials/wp_result_template.php';
                  else:
                    include plugin_dir_path(__FILE__). 'partials/wp_result_template.php';
                  endif;
                endif;?>
          <?php
              endforeach; ?>

          <?php
            $total_pages = ceil($content_resultset->getNumFound()/$control_attrs['limit']);
            if ($total_pages > 1):
           ?>
          <div class="pagination">
            <?php
            odm_get_template('pagination_solr', array(
                          "current_page" => $param_page,
                          "total_pages" => $total_pages
                        ),true); ?>
          </div>
          <?php
            endif;
          endif; ?>      
  			</div> <!-- end of eleven columns -->
  		</div> <!-- end of row -->

  <?php
      endif; ?>
  </section> <!-- end of container -->
	<script>

    jQuery(document).ready(function() {

      jQuery( "#accordion" ).accordion({
        collapsible: true,
        active: false,
        header: "h2",
        heightStyle: "content"
      });

      jQuery( ".filter_box" ).select2();

      jQuery('#search_field').autocomplete({
        source: function( request, response ) {
          var host = jQuery('#search_field').data("solr-host");
          var scheme = jQuery('#search_field').data("solr-scheme");
          var path = jQuery('#search_field').data("solr-path");
          var core_wp = jQuery('#search_field').data("solr-core-wp");
          var core_ckan = jQuery('#search_field').data("solr-core-ckan");
          var url_wp = scheme + "://" + host  + path + core_wp + "/suggest";
          var url_ckan = scheme + "://" + host  + path + core_ckan + "/suggest";

          jQuery.ajax({
            url: url_wp,
            data: {'wt':'json', 'q':request.term, 'json.wrf': 'callback'},
            dataType: "jsonp",
            jsonpCallback: 'callback',
            contentType: "application/json",
            success: function( data ) {
              console.log("wp autocompletion suggestions: " + JSON.stringify(data));
              var options = [];
              if (data){
                if(data.spellcheck){
                  var spellcheck = data.spellcheck;
                  if (spellcheck.suggestions){
                    var suggestions = spellcheck.suggestions;
                    if (suggestions[1]){
                      var suggestionObject = suggestions[1];
                      options = suggestionObject.suggestion;
                    }
                  }
                }
              }
              jQuery.ajax({
                url: url_ckan,
                data: {'wt':'json', 'q':request.term, 'json.wrf': 'callback'},
                dataType: "jsonp",
                jsonpCallback: 'callback',
                contentType: "application/json",
                success: function( data ) {   
                  console.log("ckan autocompletion suggestions: " + JSON.stringify(data));
                  if (data){
                    if(data.spellcheck){
                      var spellcheck = data.spellcheck;
                      if (spellcheck.suggestions){
                        var suggestions = spellcheck.suggestions;
                        if (suggestions[1]){
                          var suggestionObject = suggestions[1];
                          options = options.concat(suggestionObject.suggestion);
                        }
                      }
                    }
                  }
                  response( options );
                }
              });
            }
          });
        },
        minLength: 2,
        select: function( event, ui ) {
          var terms = this.value.split(" ");
          terms.pop();
          terms.push( ui.item.value );
          this.value = terms.join( " " );
          return false;
        }
      });
    });

	</script>

<?php get_footer(); ?>
