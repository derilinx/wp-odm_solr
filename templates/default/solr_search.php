<?php get_header(); ?>

<?php
  include_once dirname(dirname(plugin_dir_path(__FILE__))).'/utils/solr-wp-manager.php';
  include_once dirname(dirname(plugin_dir_path(__FILE__))).'/utils/solr-ckan-manager.php';

  $param_query = !empty($_GET['s']) ? $_GET['s'] : null;
  $param_type = (isset($_GET['type']) && !empty($_GET['type'])) ? $_GET['type'] : null;
  $param_license = !empty($_GET['license']) ? $_GET['license'] : null;
  $param_taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : null;
  $param_language = isset($_GET['language']) ? $_GET['language'] : null;
  $param_page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
  $param_country = odm_country_manager()->get_current_country() == 'mekong' && isset($_GET['country']) ? $_GET['country'] : odm_country_manager()->get_current_country();
	$param_sorting = isset($_GET['sorting']) ? $_GET['sorting'] : 'score';

  //================ Filter Values ===================== //

  $taxonomy_list = odm_taxonomy_manager()->get_taxonomy_list();
  $countries = odm_country_manager()->get_country_codes();
  $languages = odm_language_manager()->get_supported_languages();
  $license_list = wpckan_get_license_list();

  //================ Build query attributes ===================== //

  $attrs = [];
  $control_attrs = array(
    "sorting" => $param_sorting,
    "limit" => 15,
    "page" => 1
  );

  if ($param_type || $param_page) {
    $control_attrs['limit'] = 15;
    $control_attrs['page'] = $param_page;
  }

  //================ Search types ===================== //

  $supported_search_types = array(
    'ckan' => array( 'dataset', 'library_record', 'laws_record', 'agreement'),
    'wp' => array( 'map-layer', 'news-article', 'topic', 'profiles', 'story', 'announcement', 'site-update')
  );

    //================ Run queries and gather both results and facets ===================== //

    $results = [];
    $facets = [];

    $facets_mapping = array(
      "categories" => "vocab_taxonomy",
      "country_site" => "extras_odm_spatial_range",
      "odm_language" => "extras_odm_language",
      "tags" => "extras_odm_keywords",
      "vocab_taxonomy" => "vocab_taxonomy",
      "extras_odm_spatial_range" => "extras_odm_spatial_range",
      "extras_odm_language" => "extras_odm_language",
      "extras_odm_keywords" => "extras_odm_keywords",
      "license_id" => "license_id"
    );

    // -------------- Get all results --------------- //
    foreach ($supported_search_types as $key => $list):
      $attrs = [];
      $result = null;
      $imploded_types = "(\"" . implode("\" OR \"", $list) . "\")";


      if ($key == 'ckan'):
        //Taxonomy
        if (!empty($param_taxonomy) && $param_taxonomy != 'all') {
          $attrs["vocab_taxonomy"] = $param_taxonomy;
        }

        // Language
        if (!empty($param_language) && $param_language != 'all') {
          $attrs["extras_odm_language"] = $param_language;
        }

        // Country
        if (!empty($param_country) && $param_country != 'mekong' && $param_country != 'all') {
          $attrs["extras_odm_spatial_range"] = $countries[$param_country]['iso2'];
        }

        //License
        if (!empty($param_license) && $param_license != 'all') {
          $attrs['license_id'] = $param_license;
        }

        $attrs["dataset_type"] = $imploded_types;
        $attrs["capacity"] = "public";

        $control_attrs['limit'] = 15;
        $control_attrs['page'] = 1;

        $result = WP_Odm_Solr_CKAN_Manager()->query($param_query,$attrs,$control_attrs);
      else:

        //Taxonomy
        if (!empty($param_taxonomy) && $param_taxonomy != 'all') {
          $attrs["categories"] = $param_taxonomy;
        }

        // Language
        if (!empty($param_language) && $param_language != 'all') {
          $attrs["odm_language"] = $param_language;
        }

        // Country
        if (!empty($param_country) && $param_country != 'mekong' && $param_country != 'all') {
          $attrs["country_site"] = $param_country;
        }

        $attrs["type"] = $imploded_types;
        $result = WP_Odm_Solr_WP_Manager()->query($param_query,$attrs,$control_attrs);
      endif;

      $top_tier_taxonomic_terms = odm_taxonomy_manager()->get_taxonomy_top_tier();
      $results[$key] = $result["resultset"];
        foreach ($result["facets"] as $facet_key => $facet):
          $facet_key_mapped = $facets_mapping[$facet_key];
          if (!isset($facets[$facet_key_mapped])):
            $facets[$facet_key_mapped] = [];
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
            if (!isset($facets[$facet_key_mapped][$facet_value])):
              $facets[$facet_key_mapped][$facet_value] = 0;
            endif;
            $facets[$facet_key_mapped][$facet_value] += $count;
          endforeach;
        endforeach;
      endforeach; ?>

<section class="container">
  <div class="row">
    <div class="sixteen columns data-advanced-filters">
      <form>
      <?php include plugin_dir_path(__FILE__).'partials/filters.php'; ?>
    </dvi>
  </div>
</section>

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

    //================ show filters ===================== // ?>

    <div class="row">

      <div class="twelve columns">
        <?php
            $resultset = $results["wp"];
            $resultcount = ($resultset) ? $resultset->getNumFound() : 0;

            if (isset($resultset) && $resultcount > 0):
              foreach ($resultset as $document):
                include plugin_dir_path(__FILE__). 'partials/wp_default_result_template.php';
              endforeach; ?>

          <?php
            $total_pages = ceil($resultset->getNumFound()/$control_attrs['limit']);
            if ($total_pages > 1): ?>
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
			</div>

      <div class="four columns">
        <?php
            $resultset = $results["ckan"];
            $resultcount = ($resultset) ? $resultset->getNumFound() : 0;

            if (isset($resultset) && $resultcount > 0):
              foreach ($resultset as $document):
                include plugin_dir_path(__FILE__). 'partials/ckan_default_result_template.php';
              endforeach; ?>

          <?php
          endif; ?>
  		</div>

	</div>

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

      jQuery('#search_field_old').autocomplete({
        source: function( request, response ) {
          var host = jQuery('#search_field_old').data("solr-host");
          var scheme = jQuery('#search_field_old').data("solr-scheme");
          var path = jQuery('#search_field_old').data("solr-path");
          var core_wp = jQuery('#search_field_old').data("solr-core-wp");
          var core_ckan = jQuery('#search_field_old').data("solr-core-ckan");
          var url_wp = scheme + "://" + host  + path + core_wp + "/suggest";
          var url_ckan = scheme + "://" + host  + path + core_ckan + "/suggest";

          jQuery.ajax({
            url: url_wp,
            data: {'wt':'json', 'q':request.term, 'json.wrf': 'callback'},
            dataType: "jsonp",
            jsonpCallback: 'callback',
            contentType: "application/json",
            success: function( data ) {
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