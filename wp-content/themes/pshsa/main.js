jQuery(function($) {

    var $chosenDropDownNoSearch = $('select.orderby, select.per_page, select.wppp-select'),
        $chosenDropDownWithSearch = $('select#calc_shipping_state'),
        $mainLogo = $('#branding a'),
        $blackClose = $('.woof_radio_term_reset'),
        $blueClose = $('.woof_products_top_panel .woof_remove_ppi'),
        $domRemove = $('#searchwoofdiv>p, #sidebar .woof_container_pa_sector, #sidebar .woof_container_pa_all-courses, #sidebar .woof_container_pa_region, #sidebar .woof_container_pa_course-city, .gridlist-toggle, .single-product.woocommerce-page #sidebar, #searchwoofdiv .woof_container_pa_month, #searchwoofdiv .woof_container_pa_training, #searchwoofdiv .woof_container_pa_region, #searchwoofdiv .woo_submit_search_form_container'),
        $shopDescriptionWrap = $('.woocommerce-page #topcontainterfilter h1, .woocommerce-page #topcontainterfilter p, .woocommerce-page #topcontainterfilter img'),
        $shopFilter = $('.woocommerce-page #content .woof_products_top_panel'),
        $shopFilterResetButton = $('#woo_reset_search_form_custom'),
        $shopRightSideItems = $('.woocommerce-page .woocommerce-result-count, .woocommerce-page .form-wppp-select.products-per-page, .woocommerce-page .woocommerce-ordering, .woocommerce-page .products.list, .woocommerce-page .woocommerce-pagination'), // these items need to be wrapped in a SECTION
        $shopGridControls = $('.woocommerce-page .woocommerce-result-count, .woocommerce-page .form-wppp-select.products-per-page, .woocommerce-page .woocommerce-ordering'),
        $trainingTypeFilter = $('.woocommerce-page .sidebar-content .woof_container_inner_training ul li'),
        $prodDetailTableHeadColumns = $('table.groupedproducttablecls thead tr th');


    // Main Logo SVG
    $mainLogo.addClass('icon-LOGO-PSHSAca-Full-Logo-Tagline');

    // Frontend Chosen selects
    $chosenDropDownNoSearch.chosen({
        disable_search: true
    });

    $chosenDropDownWithSearch.chosen({

    });

    // Black close button from SVG
    $blackClose.addClass('icon-closesymbol');

    //Blue Close Button
    $blueClose.addClass('icon-closesymbol_blue');

    //remove non-needed items
    $domRemove.remove();

    //shop page description wrap
    $shopDescriptionWrap.wrapAll('<section class="descriptionCopy"></section>');

    //shop page Right grid wrap
    $shopRightSideItems.wrapAll('<section class="productGrid"></section>');
    //shop page product grid controls
    $shopGridControls.wrapAll('<div class="gridControl"></div>');

    //shop page left rail collaspe for mobile
    // the Training and Month heading will be the clickable states
    if ($('html').hasClass('touch')) {

        $('.woof_container_radio h4').on('click', function(e) {

            if ($(this).hasClass('closed')) {
                $(this).removeClass().addClass('open');
                $(this).next().find('ul').show();
            } else if ($(this).hasClass('open')) {
                $(this).removeClass().addClass('closed');
                $(this).next().find('ul').hide();
            } else {
                $(this).removeClass().addClass('open');
                $(this).next().find('ul').show();
            }

        });
    };

    // Training Filter Tool Tips   
    // $trainingTypeFilter.each(function(i, v) {        
    //     var $this = $(this);
    //     $this.attr('data-id', i+1);
    //     $this.append('<span class="icon_info fa fa-info"><span class="toolTip">copy copy copy</span></span>');                
    // });

    // Product Detail table

    // we grab the first three letters of the column header, and make that the class. This is needed
    // to fix the column widths. 
    $prodDetailTableHeadColumns.each(function(i,v){
        var label = $(this).text().substring(0, 3).toLowerCase();
        $(this).addClass(label);        
    })


    // Google Analytic event tracking
    var $topBar = $('#top-bar a'),
        $homeSliderPanel = $('.home .slides li[class*="slide-"]'),
        $homeCTA = $('.home .mastHeadCTA'),
        $homeeLearningShowCase = $('.home .featuredProduct'),
        $homeeLearningThumbnails = $('.home .productByCategory li');

    // top bar links
    $topBar.on('click', function(e) {
        $this = this;
        var c = 'Top Bar',
            a = 'Click Event'
        l = $this.text;
        // pass data to the track event function
        trackEvent(c, a, l);
    });

    // home page slider
    if ($homeSliderPanel) {
        $homeSliderPanel.each(function(i, v) {
            $(this).attr('data-slideposition', i + 1); // set the data attribute 'slidepostion' with a value that matches         
        });
        // track links in a slider, passing slider title, position and link copy to GA
        $('a', $homeSliderPanel).on('click', function(e) {
            var $slideHolder = $(this).closest('li[class*="slide-"]'), // we need the slide holder selecter
                $slidePosition = 'Slide #' + $slideHolder.attr('data-slideposition'), // we grab the slide postion from the data attribute
                $slideTitle = $slideHolder.find('.f24').length ? $slideHolder.find('.f24').html() : 'No Title', //slide title, if there is no title, we say No Title
                $linkCopy = $(this).html(); // we grab the link copy            
            trackEvent('Home Page Slider', 'Click Event', $slidePosition + ' | Slide Title: ' + $slideTitle + ' | Link Copy: ' + $linkCopy);
        });
    };

    // home page CTAs
    if ($homeCTA) {
        $('a', $homeCTA).on('click', function(e) {
            // resolve parent tag of click.
            var $parent = $(this).parent(),
                $ctaTitle;

            // CTA Thumbnail click
            if ($parent.hasClass('mastHeadCTA-image')) {
                $ctaTitle = $parent.next().children().first().text();
            }
            // CTA button click
            else if ($parent.hasClass('mastHeadCTA-copy')) {
                $ctaTitle = $parent.children().first().text();
            }
            // CTA Title click
            else {
                $ctaTitle = $(this).text();
            }

            // call track event function
            trackEvent('Home Page CTA', 'Click Event', 'CTA Tite: ' + $ctaTitle);

        })
    };

    // home page eLearning CTAs

    // showcase
    if ($homeeLearningShowCase) {
        $('a', $homeeLearningShowCase).on('click', function(e) {
            // get the element title
            var $ctaTitle = $(this).parent().find('h1').text();
            // call track event function
            trackEvent('Home Page eLearning showcase', 'Click Event', 'CTA Title: ' + $ctaTitle)
        })
    };
    // thumbnail posts

	// Track clicks of view more button for each affiliate
    $('a.readmorebtncls').on('click', function(e) {
	    if (getUtmSource() != ''){  // call track event function
	        trackEvent( $(this).text() + ' button', 'Click Event', 'Affiliate: ' + getUtmSource() );
        }
    });
    
    // Get the utm_source from the url to get the affiliate
    function getUtmSource(){
	    var utmSource = '';
	    for (var i in window.location.search.substring(1).split("&")) {
	         for(var j in window.location.search.substring(1).split("&")[i].split("=")){
		         if (window.location.search.substring(1).split("&")[i].split("=")[0] == 'utm_source'){
		         	 utmSource = window.location.search.substring(1).split("&")[i].split("=")[1];
	         	 }
	         }
        }
        return utmSource;
    }
    
    // track all the links of page: http://www.pshsa.ca/workplace-violence-leadership-table-3/
    $('a.ga_track_pdf_cls').on('click', function(e) {	    
	    //e.preventDefault(); // stop the hyperlink click
	    var filePath    =  $(this).attr("href");
	    var downloadURL = window.location.protocol + '//' + window.location.hostname + '/wp-content/themes/pshsa/download.php?filepath=' + filePath;
	     
	    window.open(downloadURL);
        trackEvent('download', 'Workplace Violence Documents', $(this).text());
    });
    
    // this is the base function that passes event data to google analytics
    function trackEvent(c, a, l) {
         //console.log(c,a,l);
        __gaTracker('send', 'event', c, a, l);
    }
    
});

//# sourceMappingURL=main.js.map