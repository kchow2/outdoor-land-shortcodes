<?php

/*
  Plugin Name: Outdoor Land Shortcodes
  Plugin URI:  http://www.google.ca
  Description: This plugin implements all the custom shortcode functionality for the 'Outdoor Lands' website. Usage: [popular-loc parent="continent" parentterm="north-america" target="country" max="50"]
  Version:     0.1
  Author:      Kevin Chow
  Author URI:  http://www.google.ca
 */
defined('ABSPATH') or die('No script kiddies please!');

//popular-loc shortcode.
//Usage: [popular-loc parent="continent" parentterm="north-america" target="country" max="50"]
function popular_loc_sc($atts) {
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

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'popular-loc: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['target']) || !array_key_exists($atts['target'], $taxonomyLookup))
        return 'popular-loc: target attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'popular-loc: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular " . $pluralsLookup[$atts['target']];

    $title = $atts['title'];
    $parentTaxSlug = $taxonomyLookup[$atts['parent']];
    $parentTermSlug = $atts['parentterm'];
    $targetPostType = $atts['target'];
    $targetTaxSlug = $taxonomyLookup[$atts['target']];
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'post_type' => $targetPostType,
        'posts_per_page' => -1, //can't limit the query here, since some locations returned will have 0 activities and won't be displayed.
        'orderby' => 'title',
        'order' => 'ASC',
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
                'post_type' => 'activity',
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
    $res = popular_loc_format_result($title, $totalActivityCount, $resultData);
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

add_shortcode('popular-loc', 'popular_loc_sc');

//helper function to format the HTML output from the found results
function popular_loc_format_result($title, $totalActivityCount, $data) {
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

//popular-activities shortcode. Displays a list of activity categories for a location, with the article count for each category.
//Usage: [popular-activities parent="continent" parentterm="north-america" max="50"]
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
    $targetPostType = 'activity-category';
    $parentTermSlug = $atts['parentterm'];
    $targetTaxSlug = 'tr-activity-category';
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'post_type' => $targetPostType,
        'posts_per_page' => -1, //return all the categories here, and filter them later...
        'orderby' => 'title',
        'order' => 'ASC',
    );

    $query = new WP_Query($queryArgs);
    $categoryCount = $query->found_posts;
    $totalActivityCount = 0;
    $categoriesDisplayed = 0;
    $categoriesHidden = 0;
    $resultData = array();
    if ($query->have_posts()) {
        while ($query->have_posts() and $categoriesDisplayed < $maxPostCount) {
            $query->the_post();
            $postTitle = the_title("", "", false);
            $postUrl = get_permalink();
            $postSlug = basename(get_permalink());

            //for each category do another query to find the number of 'activities' for that category with the same location
            $activityQueryArgs = array(
                'post_type' => 'activity',
                'posts_per_page' => -1,
                'tax_query' => array(
                    'relationship' => 'AND',
                    array(
                        'taxonomy' => $targetTaxSlug,
                        'field' => 'slug',
                        'terms' => array($postSlug),
                    ),
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
    }

    //Generate the output
    $res = popular_activities_format_result($title, $totalActivityCount, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "targetTaxSlug=$targetTaxSlug<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Locations found: $categoryCount<br>";
    //$res .= "Locations displayed: $categoriesDisplayed<br>";
    //$res .= "Locations hidden: $categoriesHidden<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-activities', 'popular_activities_sc');

//helper function to format the HTML output from the found results
function popular_activities_format_result($title, $totalActivityCount, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations tr-pop-destinations" style="overflow:auto;">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul style="padding-left:0px; overflow:hidden;">';
    foreach ($data as $category) {
        $url = $category['url'];
        $title = $category['title'];
        $activityCount = $category['activity_count'];

        $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
        $res .= "<a href=\"$url\">$title</a>";
        $res .= ' (' . $activityCount . ') ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    $res .= '<div style="clear:both;"></div>';
    return $res;
}

//top-contributors shortcode. Displays a list of top contributors for a location or activity category.
//Usage: [top-contributors parent="continent" parentterm="north-america" max="50"]
//Usage: [top-contributors parent="activity-category" parentterm="cycling" max="50"]
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
    $targetPostTypes = array('activity', 'guide', 'gear-review');
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'posts_per_page' => -1, //return all the categories here, and filter them later...
        'orderby' => 'name',
        'order' => 'ASC',
    );

    $query = new WP_User_Query($queryArgs);
    $userCount = $query->get_total();
    $userQueryResults = $query->get_results();

    $usersDisplayed = 0;
    $usersHidden = 0;
    $resultData = array();
    foreach ($userQueryResults as $user) {
        $authorUrl = get_author_posts_url( $user->ID );//'/authors/'.$user->nickname;
        $userName = $user->nickname;
        $avatar = get_user_meta($user->ID, 'wpcf-tr-user-profile-image', true);
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
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Users found: $userCount<br>";
    //$res .= "Users displayed: $usersDisplayed<br>";
    //$res .= "Users hidden: $usersHidden<br>";
    wp_reset_postdata();
    return $res;
}
add_shortcode('top-contributors', 'top_contributors_sc');

//helper function to format the HTML output from the found results
function top_contributors_format_result($title, $data) {
    if (empty($data))
        return '';

    $itemsPerRow = 4;
    $numRows = ceil(count($data) / $itemsPerRow);

    $res = '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    for ($row = 0; $row < $numRows; $row++) {
        $res .= '<div class="row">';
        for ($i = 0; $i < $itemsPerRow; $i++) {

            $dataIndex = $row * $itemsPerRow + $i;
            if ($dataIndex >= count($data))
                break;

            $url = $data[$dataIndex]['url'];
            $name = $data[$dataIndex]['name'];
            $postCount = $data[$dataIndex]['post_count'];
            $avatar = $data[$dataIndex]['avatar'];
            if(!$avatar) 
                $avatar = 'http://test4.thenextturn.com/wp-content/uploads/2015/05/almeria_92658m1.jpg';

            $res .= '<div class="col-sm-3" height="100px" style="padding-left:15px; padding-right:5px;">';
            //$res .= '<div style="margin-right:-10px; margin-left:-10px;">';
            $res .= '<a href="' . $url . '" class="tr-users-column">';
            $res .= "<image alt=\"$name\" title=\"$name\" src=\"$avatar\" max-height=\"85px\">" . '<br>';
            $res .= "<h3>$name ($postCount)</h3>";
            $res .= '</a>';
            //$res .= '</div>';
            $res .= '</div>';
        }
        $res .= '</div>';//<div class="row">
    }
    $res .= '</div>';//<div class="tr-pop-locations tr-pop-destinations">
    return $res;
}

//popular-cat-loc shortcode.
//Usage: [popular-loc category="cycling" target="country" max="50"]
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
            //echo $locationTerms;
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


//author-footprint shortcode.
//Usage: [author-footprint author="<author_id>"]
function author_footprint_sc($atts) {

    if (!isset($atts['author']) || !is_numeric($atts['author']))
        return 'author-footprint: author attribute not valid! Must be a valid author Id.';
    $authorId = $atts['author'];
    $title = 'My Footprint';
    //$maxPostCountPerSection = 15;
    
    //get all the activities for this category
    $queryArgs = array(
        'post_type' => 'activity',
        'author' => $authorId,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    );
    $query = new WP_Query($queryArgs);
    $countryActivityCount = array();
    $regionActivityCount = array();
    $cityActivityCount = array();
    $destinationActivityCount = array();
    $locSlugs = array();
    while ($query->have_posts()) {
        $query->the_post();

        $countryTerms = wp_get_post_terms(get_the_ID(), 'tr-country');
        $cityTerms = wp_get_post_terms(get_the_ID(), 'tr-city');
        $regionTerms = wp_get_post_terms(get_the_ID(), 'tr-region');
        $destinationTerms = wp_get_post_terms(get_the_ID(), 'tr-destination');
        
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
    
    //post counts for tips nad reviews
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
        //get the metadata for the current location page.
        //We need the subregion, region, city, state, country, continent of this location
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
