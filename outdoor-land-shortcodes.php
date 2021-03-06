<?php

/*
  Plugin Name: Outdoor Land Shortcodes
  Plugin URI:  http://www.google.ca
  Description: This plugin implements all the custom shortcode functionality for the 'Outdoor Lands' website.
  Version:     0.1
  Author:      Kevin Chow
  Author URI:  http://www.google.ca
 */
defined('ABSPATH') or die('No script kiddies please!');

/**popular-loc shortcode.
 * Usage: [popular-loc parent="continent" parentterm="north-america" target="country" max="50"]
 * This shortcode is deprecated. Use popular-loc2 instead.
 */
function popular_loc_sc($atts) {
    return "!!WARNING: [popular-loc] is deprecated. Please use [popular-loc2] instead!!";
}
add_shortcode('popular-loc', 'popular_loc_sc');

/**popular-loc shortcode.
 * Usage: [popular-loc2 parent="continent" parentterm="north-america" target="country" max="50"]
 * Generates a list of locations that are a child location of a parent location, with a count of the number of articles written about each child location.
 */
function popular_loc_sc2($atts) {
    $pluralsLookup = array(
        'destination' => 'Destinations',
        'subregion' => 'Sub-regions',
        'sub-region' => 'Sub-regions',
        'region' => 'Regions',
        'city' => 'Cities',
        'country' => 'Countries',
        'continent' => 'Continents',
        'state' => 'States',
        'route' => 'Routes',
    );
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
        'route' => 'tr-route',
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'popular-loc: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'popular-loc: parentterm attribute not set!';
    if (!isset($atts['target']) || !array_key_exists($atts['target'], $taxonomyLookup))
        return 'popular-loc: target attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular " . $pluralsLookup[$atts['target']];

    $title = $atts['title'];
    $parentTaxSlug = $taxonomyLookup[$atts['parent']];
    $parentTermSlug = $atts['parentterm'];
    $targetLocationType = $atts['target'];
    $targetTaxSlug = $taxonomyLookup[$atts['target']];
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'post_type' => 'location',
        'posts_per_page' => -1, //can't limit the query here, since some locations returned will have 0 activities and won't be displayed.
        'orderby' => 'title',
        'order' => 'ASC',
        //'meta_key'=>'tr-location-type',
        //'meta_value'=>$targetLocationType,
        'tax_query' => array(
            array(
                'taxonomy' => $parentTaxSlug,
                'field' => 'slug',
                'terms' => array($parentTermSlug),
            ),
        )
    );

    $query = new WP_Query($queryArgs);
    $locationCount = $query->found_posts;
    $totalActivityCount = 0;
    $locationsDisplayed = 0;
    $locationsHidden = 0;
    $resultData = array();

    if ($query->have_posts()) {
        while ($query->have_posts() and $locationsDisplayed < $maxPostCount) {
            $query->the_post();
            $postTitle = the_title("", "", false);
            $postUrl = get_permalink();
            $postSlug = basename(get_permalink());

            //for each location do another query to find the number of 'activities' for that location
            $activityQueryArgs = array(
                'post_type' => 'post',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => $targetTaxSlug,
                        'field' => 'slug',
                        'terms' => array($postSlug),
                    ),
                )
            );
            $activityQuery = new WP_Query($activityQueryArgs);
            $activityCount = $activityQuery->found_posts;   //only interested in the number of results, not the actual activities
            $totalActivityCount += $activityCount;

            if ($activityCount > 0) {
                $resultData[] = array('url' => $postUrl, 'title' => $postTitle, 'activity_count' => $activityCount);
                $locationsDisplayed++;
            } else {
                $locationsHidden++;
            }
        }
    }

    //Generate the output
    $res = popular_loc_format_result2($title, $totalActivityCount, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Locations found: $locationCount<br>";
    //$res .= "Locations displayed: $locationsDisplayed<br>";
    //$res .= "Locations hidden: $locationsHidden<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-loc2', 'popular_loc_sc2');

//helper function to format the HTML output from the found results
function popular_loc_format_result2($title, $totalActivityCount, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul>';
    foreach ($data as $location) {
        $locUrl = $location['url'];
        $locTitle = $location['title'];
        $activityCount = $location['activity_count'];

        $res .= '<li class="tr-pop-item">';
        $res .= "<a href=\"$locUrl\">$locTitle</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    return $res;
}

/**popular-activities shortcode. 
 * Usage: [popular-activities parent="continent" parentterm="north-america" max="50"]
 * Displays a list of activity categories for a location, with the article count for each category.
 */
function popular_activities_sc($atts) {
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state'
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'popular-activities: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'popular-activities: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular Activities";

    $title = $atts['title'];
    $parentTaxSlug = $taxonomyLookup[$atts['parent']];
    $parentTermSlug = $atts['parentterm'];
    $maxPostCount = $atts['max'];

    $categories = get_categories();
    
    $totalActivityCount = 0;
    $categoriesDisplayed = 0;
    $categoriesHidden = 0;
    $resultData = array();

    foreach($categories as $cat) {
        if($categoriesDisplayed == $maxPostCount)
            break;

        $postTitle = $cat->name;
        $postUrl = get_category_link($cat->term_id);
        $postSlug = $cat->slug;

        //for each category do another query to find the number of 'activities' for that category with the same location
        $activityQueryArgs = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'category_name' => $postSlug,
            'tax_query' => array(
                array(
                    'taxonomy' => $parentTaxSlug,
                    'field' => 'slug',
                    'terms' => array($parentTermSlug),
                ),
            )
        );
        $activityQuery = new WP_Query($activityQueryArgs);
        $activityCount = $activityQuery->found_posts;   //only interested in the number of results, not the actual activities
        $totalActivityCount += $activityCount;
        
        if ($activityCount > 0) {
            $resultData[] = array('url' => $postUrl, 'title' => $postTitle, 'activity_count' => $activityCount);
            $categoriesDisplayed++;
        } else {
            $categoriesHidden++;
        }
    }
    //Generate the output
    $res = popular_activities_format_result($title, $totalActivityCount, $resultData);
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-activities', 'popular_activities_sc');

//helper function to format the HTML output from the found results
function popular_activities_format_result($title, $totalActivityCount, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-activities">';
    $res .= '<h3 style="padding-left:15px;">' . $title . '</h3>';
            
    $res .= '<div class="col-sm-4">';
    $res .= '<ul>';
    $i = 0;
    for(; $i < count($data)/3;$i++){
        $category = $data[$i];
        $url = $category['url'];
        $title = $category['title'];
        $activityCount = $category['activity_count'];

        $res .= '<li class="tr-pop-act-item">';
        $res .= "<a href=\"$url\">$title</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    
    $res .= '<div class="col-sm-4">';
    $res .= '<ul>';
    for(; $i < 2*count($data)/3;$i++){
        $category = $data[$i];
        $url = $category['url'];
        $title = $category['title'];
        $activityCount = $category['activity_count'];

        $res .= '<li class="tr-pop-act-item">';
        $res .= "<a href=\"$url\">$title</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    
    $res .= '<div class="col-sm-4">';
    $res .= '<ul>';
    for(; $i < count($data);$i++){
        $category = $data[$i];
        $url = $category['url'];
        $title = $category['title'];
        $activityCount = $category['activity_count'];

        $res .= '<li class="tr-pop-act-item">';
        $res .= "<a href=\"$url\">$title</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';

    $res .= '</div>';//tr-pop-activities
    return $res;
}

/**top-contributors shortcode. 
 * Usage: [top-contributors parent="continent" parentterm="north-america" max="50"]
 * Usage: [top-contributors parent="activity-category" parentterm="cycling" max="50"]
 * Displays a list of top contributors for a location or activity category. This shortcode only counts posts of type 'post', 'guide', and 'gear-review'
 */
function top_contributors_sc($atts) {
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
        'route' => 'tr-route',
        'activity-category' => 'tr-activity-category'
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'top-contributors: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'top-contributors: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Top Contributors";

    $title = $atts['title'];
    $targetTaxonomy = $atts['parent'];
    $targetTerm = $atts['parentterm'];
    $targetPostTypes = array('post', 'guide', 'gear-review');
    $maxPostCount = $atts['max'];

    //get a list of all authors
    $queryArgs = array(
        'posts_per_page' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
        'role'=>'author',
    );

    $query = new WP_User_Query($queryArgs);
    $userCount = $query->get_total();
    $userQueryResults = $query->get_results();

    $usersDisplayed = 0;
    $usersHidden = 0;
    $resultData = array();
    foreach ($userQueryResults as $user) {
        $authorUrl = get_author_posts_url( $user->ID );
        $userName = $user->nickname;
        $avatar = types_render_usermeta_field('tr-user-profile-image', array('user_id'=>$user->ID, 'url'=>true, 'width'=>200, 'height'=>200, 'resize'=>'crop'));
        $postCount = 0;
        foreach ($targetPostTypes as $postType) {
            //get all posts of each type from this author for this location
            $queryArgs = array(
                'author' => $user->ID,
                'post_type' => $postType,
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomyLookup[$targetTaxonomy],
                        'field' => 'slug',
                        'terms' => $targetTerm,
                    ),
                ),
            );
            $query = new WP_Query($queryArgs);
            $postCount += $query->found_posts;
        }

        if ($postCount > 0) {
            $resultData[] = array('url' => $authorUrl, 'name' => $userName, 'post_count' => $postCount, 'avatar' => $avatar);
            $usersDisplayed++;
        } else {
            $usersHidden++;
        }

        if ($usersDisplayed == $maxPostCount)
            break;
    }
    //Generate the output
    $res = top_contributors_format_result($title, $resultData);
    wp_reset_postdata();
    return $res;
}
add_shortcode('top-contributors', 'top_contributors_sc');

//helper function to format the HTML output from the found results
function top_contributors_format_result($title, $data) {
    if (empty($data))
        return '';
    
    $count = 0;
    $closingDiv = true;
    $res = '<div class="tr-pop-locations tr-pop-destinations tr-users-container">';     //container start
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    
    foreach($data as $d){
        if($count%3==0){
            $closingDiv = false;
            $res .= '<div class="row col-lg-4 col-sm-6 tr-user-row">';
        }
        $res .= '<div class="col-xs-4 tr-user-item">';
        $url = $d['url'];
        $name = $d['name'];
        $postCount = $d['post_count'];
        $avatar = $d['avatar'];
        if(!$avatar) 
            $avatar = 'http://test4.thenextturn.com/wp-content/uploads/2015/05/almeria_92658m1.jpg';
        
        $res .= '<a href="' . $url . '">';
        $res .= "<image alt=\"$name\" title=\"$name\" src=\"$avatar\">" . '<br>';
        $res .= "<h5>$name ($postCount)</h5>";
        $res .= '</a>';
        $res .= '</div>';
        
        if($count%3==2){
            $res .= '</div>';
            $closingDiv = true;
        }
        $count++;
    }
    if(!$closingDiv){
        $res .= '</div>';
        $closingDiv = true;
    }
    $res .= '</div>';   //container end
    return $res;
}

/**popular-cat-loc shortcode.
 * Usage: [popular-loc category="cycling" target="country" max="50"]
 * Counts the number of articles that exist for a certain category per location. The location type must be 'country', 'city', 'continent', etc.
 */
function popular_cat_loc_sc($atts) {
    $pluralsLookup = array(
        'destination' => 'Destinations',
        'subregion' => 'Sub-regions',
        'sub-region' => 'Sub-regions',
        'region' => 'Regions',
        'city' => 'Cities',
        'country' => 'Countries',
        'continent' => 'Continents',
        'state' => 'States',
    );
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
    );

    if (!isset($atts['target']) || !array_key_exists($atts['target'], $taxonomyLookup))
        return 'popular-cat-loc: target attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular " . $pluralsLookup[$atts['target']];

    $title = $atts['title'];
    $categoryTermSlug = $atts['category'];
    //$parentTaxSlug = $taxonomyLookup[$atts['parent']];
    //$parentTermSlug = $atts['parentterm'];
    $targetPostType = $atts['target'];
    $targetTaxSlug = $taxonomyLookup[$atts['target']];
    $maxPostCount = $atts['max'];

    //get all the activities for this category
    $queryArgs = array(
        'post_type' => 'activity',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'tr-activity-category',
                'field' => 'slug',
                'terms' => array($categoryTermSlug),
            ),
        )
    );
    $query = new WP_Query($queryArgs);
    $totalActivityCount = $query->found_posts;
    $locationArticleCount = array();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $locationTerms = wp_get_post_terms(get_the_ID(), $targetTaxSlug);

            foreach($locationTerms as $locTerm){
                $locName = $locTerm->name;
                if(isset($locationArticleCount[$locName]))
                    $locationArticleCount[$locName]++;
                else
                    $locationArticleCount[$locName] = 1;
            }
            
            //$postTitle = the_title("", "", false);
            //$postUrl = get_permalink();
            //$postSlug = basename(get_permalink());
        }
    }

    //generate the data we will use to display the location list
    $resultData = array();
    $locationsDisplayed = 0;
    foreach($locationArticleCount as $location=>$activityCount){
        if($locationsDisplayed == $maxPostCount)
            break;
        $locationUrl = get_site_url() . '/' . $atts['target'] . '/'. $location;
        $resultData[] = array('title'=>$location, 'activity_count'=>$activityCount, 'url'=>$locationUrl);
        $locationsDisplayed++;
    }
    
    //sort the result list alphabetically
    usort($resultData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});

    //Generate the output
    $res = popular_cat_loc_format_result($title, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Total activities found: $totalActivityCount<br>";
    //$res .= "Locations found: $locationsFound<br>";
    //$res .= "Locations displayed: $locationsDisplayed<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-cat-loc', 'popular_cat_loc_sc');

//helper function to format the HTML output from the found results
function popular_cat_loc_format_result($title, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
    foreach ($data as $location) {
        $locUrl = $location['url'];
        $locTitle = $location['title'];
        $activityCount = $location['activity_count'];

        $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
        $res .= "<a href=\"$locUrl\">$locTitle</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    return $res;
}


/**author-footprint shortcode.
 * Usage: [author-footprint author="<author_id>"]
 * Counts the number of articles an author has written about countries, regions, cities, and destinations, with the location name and article count, linked to the appropriate location page.
 * Also displays the number of guides and gear reviews the author has written.
 */
function author_footprint_sc($atts) {
    if (!isset($atts['author']) || !is_numeric($atts['author']))
        return 'author-footprint: author attribute not valid! Must be a valid author Id.';
    $authorId = $atts['author'];
    $title = 'My Footprint';
    //$maxPostCountPerSection = 15;
    
    //get all posts by the author
    $queryArgs = array(
        'author' => $authorId,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    );
    $posts = get_posts($queryArgs);
    $countryActivityCount = array();
    $regionActivityCount = array();
    $cityActivityCount = array();
    $destinationActivityCount = array();
    $locSlugs = array();
    foreach($posts as $post) {
        setup_postdata( $post );
        
        $countryTerms = wp_get_post_terms($post->ID, 'tr-country');
        $cityTerms = wp_get_post_terms($post->ID, 'tr-city');
        $regionTerms = wp_get_post_terms($post->ID, 'tr-region');
        $destinationTerms = wp_get_post_terms($post->ID, 'tr-destination');
        
        //count the number of activities for each location
        foreach($countryTerms as $term){
            $locName = $term->name;
            $locSlug = $term->slug;
            if(isset($countryActivityCount[$locName]))
                $countryActivityCount[$locName]++;
            else{
                $countryActivityCount[$locName] = 1;
                $locSlugs[$locName] = $locSlug;
            }
        }
        foreach($cityTerms as $term){
            $locName = $term->name;
            $locSlug = $term->slug;
            if(isset($cityActivityCount[$locName]))
                $cityActivityCount[$locName]++;
            else{
                $cityActivityCount[$locName] = 1;
                $locSlugs[$locName] = $locSlug;
            }
        }
        foreach($regionTerms as $term){
            $locName = $term->name;
            $locSlug = $term->slug;
            if(isset($regionActivityCount[$locName]))
                $regionActivityCount[$locName]++;
            else{
                $regionActivityCount[$locName] = 1;
                $locSlugs[$locName] = $locSlug;
            }
        }
        foreach($destinationTerms as $term){
            $locName = $term->name;
            $locSlug = $term->slug;
            if(isset($destinationActivityCount[$locName]))
                $destinationActivityCount[$locName]++;
            else{
                $destinationActivityCount[$locName] = 1;
                $locSlugs[$locName] = $locSlug;
            }
        }
    }

    //generate the data we will use to display the location list
    $countryData = array();
    $cityData = array();
    $regionData = array();
    $destinationData = array();
    
    foreach($countryActivityCount as $location=>$activityCount){
        $countryData[] = array('title'=>$location, 'activity_count'=>$activityCount, 'url'=>'/country/'.$locSlugs[$location]);
    }
    foreach($cityActivityCount as $location=>$activityCount){
        $cityData[] = array('title'=>$location, 'activity_count'=>$activityCount, 'url'=>'/city/'.$locSlugs[$location]);
    }
    foreach($regionActivityCount as $location=>$activityCount){
        $regionData[] = array('title'=>$location, 'activity_count'=>$activityCount, 'url'=>'/region/'.$locSlugs[$location]);
    }
    foreach($destinationActivityCount as $location=>$activityCount){
        $destinationData[] = array('title'=>$location, 'activity_count'=>$activityCount, 'url'=>'/destination/'.$locSlugs[$location]);
    }
    
    //sort the result list alphabetically
    usort($countryData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});
    usort($cityData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});
    usort($regionData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});
    usort($destinationData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});
    
    //post counts for tips and reviews
    $guidePostCount = count_user_posts($authorId, 'guide');
    $reviewPostCount = count_user_posts($authorId, 'gear-review');

    //Generate the output
    $res = author_footprint_format_result($title, $countryData, $cityData, $regionData, $destinationData, $guidePostCount, $reviewPostCount);
    wp_reset_postdata();
    return $res;
}
add_shortcode('author-footprint', 'author_footprint_sc');

//helper function to format the HTML output from the found results
function author_footprint_format_result($title, $countryData, $cityData, $regionData, $destinationData, $guidePostCount, $reviewPostCount) {
    if (empty($countryData) && empty($cityData) && empty($regionData) && empty($destinationData))
        return '';
    $res = '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    
    if(count($countryData) > 0){
        $res .= '<h3 style="text-align:left;">' . 'Countries:' . '</h3>';
        $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
        foreach ($countryData as $location) {
            $locUrl = $location['url'];
            $locTitle = $location['title'];
            $activityCount = $location['activity_count'];

            $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
            $res .= "<a href=\"$locUrl\">$locTitle</a>";
            $res .= ' (' . $activityCount . ') ';
            $res .= '</li>';
        }
        $res .= '</ul>';
    }
    
    if(count($regionData) > 0){
        $res .= '<h3 style="text-align:left;">' . 'Regions:' . '</h3>';
        $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
        foreach ($regionData as $location) {
            $locUrl = $location['url'];
            $locTitle = $location['title'];
            $activityCount = $location['activity_count'];

            $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
            $res .= "<a href=\"$locUrl\">$locTitle</a>";
            $res .= ' (' . $activityCount . ') ';
            $res .= '</li>';
        }
        $res .= '</ul>';
    }
    
    if(count($cityData) > 0){
        $res .= '<h3 style="text-align:left;">' . 'Cities:' . '</h3>';
        $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
        foreach ($cityData as $location) {
            $locUrl = $location['url'];
            $locTitle = $location['title'];
            $activityCount = $location['activity_count'];

            $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
            $res .= "<a href=\"$locUrl\">$locTitle</a>";
            $res .= ' (' . $activityCount . ') ';
            $res .= '</li>';
        }
        $res .= '</ul>';
    }
    
    if(count($destinationData) > 0){
        $res .= '<h3 style="text-align:left;">' . 'Destinations:' . '</h3>';
        $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
        foreach ($destinationData as $location) {
            $locUrl = $location['url'];
            $locTitle = $location['title'];
            $activityCount = $location['activity_count'];

            $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
            $res .= "<a href=\"$locUrl\">$locTitle</a>";
            $res .= ' (' . $activityCount . ') ';
            $res .= '</li>';
        }
        $res .= '</ul>';
    }
    
    $res .= "<h3>Tips: $guidePostCount, Reviews: $reviewPostCount</h3><br>";
    
    $res .= '</div>';//tr-pop-locations tr-pop-destinations
    return $res;
}

/**hotel-search shortcode.
 * Usage: [hotel-search]
 * Generates a hotel search link. It searches the field 'wpcf-tr-hotel-search' for a hotel search url. If a url is defined for the current post, it uses that. 
 * Otherwise, it checks the parent locations sequentially upwards until it finds one. If no url is found for any of the parents, then this shortcode outputs nothing.
 */
function hotel_search_sc($atts) {
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'state' => 'tr-state',
        'country' => 'tr-country',
    );
    
    //check if the hotel search url is present for this location. If it is, then we are done.
    $locationName = the_title("", "", false);
    $hotelSearchUrl = get_post_meta(get_the_ID(), 'wpcf-tr-hotel-search', true);
    
    //if the $hotelSearchUrl is not present for this location, then we need to search parent locations until we find one
    if(!$locationName || !$hotelSearchUrl){
        //get the metadata for the current location page. We need the terms for each of the taxonomies in $taxonomyLookup
        $articleId = get_the_ID(); 
        foreach($taxonomyLookup as $postType=>$tax){
            
            $taxonomyTermObjs = wp_get_post_terms($articleId, $tax);
            if(!$taxonomyTermObjs)
                $taxonomyTermObjs = array();
            foreach($taxonomyTermObjs as $obj){
                $taxTermSlug = $obj->slug;
                $taxTermName = $obj->name;
                $queryArgs = array(
                    'post_type' => $postType,
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => $tax,
                            'field' => 'slug',
                            'terms' => $taxTermSlug,
                        ),
                    )
                );
                $query = new WP_Query($queryArgs);
                if ($query->have_posts()) {
                    $query->the_post();
                    $hotelSearchUrl = get_post_meta(get_the_ID(), 'wpcf-tr-hotel-search', true);
                    if(!empty($hotelSearchUrl)){
                        $locationName = $taxTermName;
                        break;
                    }
                }
            }
            if(!empty($hotelSearchUrl))
                break;
        }
    }

    //Generate the output
    $res = hotel_search_format_result($locationName, $hotelSearchUrl);
    wp_reset_postdata();
    return $res;
}
add_shortcode('hotel-search', 'hotel_search_sc');

function hotel_search_format_result($locationName, $hotelSearchUrl){
    if(!$locationName || !$hotelSearchUrl)
        return '';
    
    $res = "";
    $res.= '<div class="tr-hotel-search">';
    $res .= '<a href="'.$hotelSearchUrl.'" target="_blank" class="btn btn-primary">';
    $res .= 'Compare Hotels in<br>'.$locationName;
    $res .= '</a>';
    $res .= '</div>';
    return $res;
}

/**activity-post-map shortcode
 * Usage: [activity-post-map slug="Canada" type="country" (activity="cycling,hiking") (author="1") (limit="30")]
 * Generates a map with markers for posts in the same location as the parent article. 
 * For each post, the most specific location is determined, and posts with the same location are merged into a single marker.
 * If there are no posts found, the map does not show up (intentionally).
 * The actvity and author parameters are optional and can be used to filter the results by activity category and author. If they are not defined, it will return results from all activities and authors. 
 * The limit parameter limits the max posts per location, not the total posts.
 */
function activity_post_map_sc($atts) {
    $taxonomyLookup = array(
        'route'=>'tr-route',
        'city'=>'tr-city',
        'destination'=>'tr-destination',
        'sub-region'=>'tr-subregion',
        'state'=>'tr-state',
        'region'=>'tr-region',
        'country'=>'tr-country',
        'continent'=>'tr-continent',
    );
    
    if(!isset($atts['type']) and isset($atts['slug'])){
        return htmlspecialchars('Usage: [activity-post-map slug="Canada" type="country" (activity="cycling,hiking") (author="1") (limit="30")]');
    }
    $limit = isset($atts['limit']) ? intval($atts['limit']) : 10;
    
    $queryArgs = array(
        'post_type' => 'post',
        'posts_per_page'=>-1,   //apparently leaving this parameter out results in the default limit of 10 results returned!
    );
    
    if(isset($atts['slug'])){
        $queryArgs['tax_query'] = array(
            array(
                'taxonomy' => $taxonomyLookup[$atts['type']],
                'field' => 'slug',
                'terms' => $atts['slug'],
            ),
        );
    }
    if(isset($atts['author'])){
        $queryArgs['author'] = intval($atts['author']);
    }
    //$atts['activity'] - a comma separated list of category slugs.
    if(isset($atts['activity'])){
        $cats = array();
        $parentCats = explode(",",$atts['activity']);
        foreach($parentCats as $parentCatName){
            $parentCat = get_category_by_slug($parentCatName);
            if($parentCat == null){
                return "activity_post_map: category '".$parentCatName."' not found.";
            }
            $parentId = $parentCat->term_id;
            $cats[] = $parentId;
            $childCats = get_categories(array('parent'=>$parentId));
            foreach($childCats as $childCat){
                $cats[] = $childCat->term_id;
            }
        }
        $queryArgs['category__in'] = $cats;
    }

    $locations = array();
    $postsByLocationID = array();
    $posts = get_posts($queryArgs);
    foreach($posts as $post1){
        
        $loc = getMostRelevantLocation($post1->ID, array_values($taxonomyLookup));    //returns a post object
        if($loc === null) {
            continue;
        }
        $locations[$loc->ID] = $loc;
        
        //limit the number of posts per location
        if(array_key_exists($loc->ID, $postsByLocationID) and count($postsByLocationID[$loc->ID]) >= $limit)
            break;
        
        $postsByLocationID[$loc->ID][] = array('ID'=>$post1->ID, 'title'=>$post1->post_title, 'url'=>get_permalink($post1));
    }
    
    //if there are no markers, skip rendering the map
    if(count($postsByLocationID) === 0){
        return "";
    }
    
    //results - display the locations on a map. each location can have 1 or more posts, which are combined into a single marker.
    $res = '<div class="activity-post-map">';
    $mapID = "map-location-posts".rand().rand();    //generate a unique map id
    $res .= do_shortcode('[wpv-map-render map_id="'.$mapID.'" map_height="450px" scrollwheel="off"]');
    foreach($postsByLocationID as $locID=>$posts){
        $mapAddress = get_post_field( "wpcf-tr-map-address", $locID );
        if(strlen($mapAddress) == 0){
            //if the "wpcf-tr-map_address" field of the location is not filled out, then the map marker will fail to show up on the map.
            continue;
        }

        //location map markers
        $locName = $locations[$locID]->post_title;
        
        $locUrl = get_post_permalink($locations[$locID]->ID);
        $postsStr = '<div class="tr-marker-title"><a href="'.$locUrl.'" target="_blank">'.$locName.'</a></div><br>';
        foreach($posts as $p){
            $postsStr .= '<a href="'.$p['url'].'" target="_blank">' . $p['title'] . "</a><br>";
        }
        $res .= do_shortcode(sprintf('[wpv-map-marker map_id=%s marker_id="marker-%s" marker_field="wpcf-tr-map-address" id="%d"]%s[/wpv-map-marker]', $mapID, $locations[$locID]->post_name, $locID, $postsStr));    
    }
    $res .= "</div>";
    wp_reset_postdata();
    return $res;
}
add_shortcode('activity-post-map', 'activity_post_map_sc');

function getMostRelevantLocation($postID, array $taxonomies){
    $termList = wp_get_post_terms($postID, $taxonomies);
    if(count($termList) > 0){
        $minIndex = count($taxonomies);
        $minTerm = null;

        //pick the most specific location of the article, according to the order in $taxonomyLookup
        foreach($termList as $t){
            $index = array_search($t->taxonomy, $taxonomies);
            if($index !== FALSE and $index < $minIndex){
                $minIndex = $index;
                $minTerm = $t;
            }
        }
        if($minTerm != null){
            $queryArgs = array(
                'post_type'=>'location', // substr($minTerm->taxonomy, 3) remove the 'tr-' in front of the term
                'tax_query' => array(
                    array(
                            'taxonomy' => $minTerm->taxonomy,
                            'field' => 'slug',
                            'terms' => $minTerm->slug
                    )
                )
            );
            $posts = get_posts($queryArgs);
            foreach($posts as $post){
                //should be either 0 or 1 location CPT entry
                return $post;
            }
        }
    }
    return null;
}
