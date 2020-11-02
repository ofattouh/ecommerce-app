<div class="item">
    <?php
    $o = new wpdreamsText("generic_filter_label", "Generic filters label text", $sd['generic_filter_label']);
    $params[$o->getName()] = $o->getData();
    ?>
</div>
<div class="item">
    <?php
    $o = new wd_DraggableFields("frontend_fields", "Generic filters", array(
        "value"=>$sd['frontend_fields'],
        "args" => array(
            "show_checkboxes" => 1,
            "show_display_mode" => 1,
            "show_labels" => 1,
            'fields' => array(
                'exact'     => 'Exact matches only',
                'title'     => 'Search in title',
                'content'   => 'Search in content',
                'excerpt'   => 'Search in excerpt',
                'comments'  => 'Search in comments'
            ),
            'checked' => array('title', 'content', 'excerpt')
        )
    ));
    $params[$o->getName()] = $o->getData();
    ?>
</div>