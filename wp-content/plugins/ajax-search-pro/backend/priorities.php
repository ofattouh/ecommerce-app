<?php
/* Prevent direct access */
defined('ABSPATH') or die("You can't access this file directly.");

$args = array(
    'public'   => true,
    '_builtin' => false
);

$output = 'names'; // names or objects, note names is the default
$operator = 'or'; // 'and' or 'or'

$post_types = array_merge(array('all'), get_post_types( $args, $output, $operator ));

$blogs = array();
if (function_exists('wp_get_sites'))
    $blogs = wp_get_sites();

?>
<script>
    jQuery(document).ready(function($){
        $("#p_asp_submit").on('click', function(e){
            e.preventDefault();
            var data = {
                action: 'ajaxsearchpro_priorities',
                options: $("#asp_priorities").serialize(),
                ptask: "get"
            };
            $('#p_asp_loader').css('display', 'block');
            $('#p_asp_results').fadeOut(10);
            var post = $.post(ASP.backend_ajaxurl, data, function (response) {
                response = response.replace(/^\s*[\r\n]/gm, "");
                response = response.match(/!!PASPSTART!!(.*[\s\S]*)!!PASPEND!!/)[1];
                response = JSON.parse(response);
                var html = '';

                $.each(response, function(k, v){
                    var input = "<input type='text' name='priority[" + v.id + "]' style='width:40px;' value='100' />";
                    var old_priority = "<input type='hidden' name='old_priority[" + v.id + "]' value='100'/>";
                    if (typeof (v.priority) != 'undefined') {
                        input = "<input type='text' style='width:40px;' name='priority[" + v.id + "]' value='" + v.priority + "'/>";
                        old_priority = "<input type='hidden' name='old_priority[" + v.id + "]' value='" + v.priority + "'/>";
                    }
                    html += "<div class='p_asp_row'><p class='p_asp_title'>["+ v.id +"] " + v.title + "</p><p class='p_asp_priority'><label>Priority</label>" + input + old_priority + "</p><p class='p_asp_date'>" + v.date + "</p><p class='p_asp_author'>" + v.author + "</p></div>";
                });
                if (html == '') {
                    $('#p_asp_loader').css('display', 'none');
                    $('#p_asp_results').html('<p style="text-align:center;">No results!</p>');
                    return true;
                }
                html += "<input type='hidden' name='p_blogid' value='"+$('select[name="p_asp_blog"]').val()+"'>";
                $('#p_asp_results').html("<div class='p_row_header_footer'><p>Post Title/Date/Author</p><input type='submit' class='p_asp_save' value='Save changes!'></div><form name='asp_priorities_list' id='asp_priorities_list' method='post'>" + html + "</form><div class='p_row_header_footer'><p>Post Title/Date/Author</p><input type='submit' class='p_asp_save' value='Save changes!'></div>");
                $('#p_asp_loader').css('display', 'none');
                $('#p_asp_results').fadeIn(150);
            }, "text");
        });

        $('#p_asp_results').on('click', '.p_asp_save', function(e){
            e.preventDefault();
            var $this = $(this);
            var data = {
                action: 'ajaxsearchpro_priorities',
                options: $("#asp_priorities_list").serialize(),
                ptask: "set"
            };
            $this.prop('disabled', true);
            $('#p_asp_results').fadeOut(10);
            $('#p_asp_loader').css('display', 'block');
            var post = $.post(ASP.backend_ajaxurl, data, function (response) {
                response = response.replace(/^\s*[\r\n]/gm, "");
                response = response.match(/!!PSASPSTART!!(.*[\s\S]*)!!PSASPEND!!/)[1];
                response = JSON.parse(response);
                $this.prop('disabled', false);
                $('#p_asp_loader').css('display', 'none');
                $("#p_asp_submit").click();
            }, "text");
        });
    });
</script>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'settings/assets/options_search.css?v='.ASP_CURR_VER; ?>" />
<div id="wpdreams" class='wpdreams wrap<?php echo isset($_COOKIE['asp-accessibility']) ? ' wd-accessible' : ''; ?>'>

    <?php if (wd_asp()->updates->needsUpdate()): ?>
        <p class='infoMsgBox'>Version <strong><?php echo wd_asp()->updates->getVersionString(); ?></strong> is available.
            Download the new version from Codecanyon. <a target="_blank" href="http://wpdreams.gitbooks.io/ajax-search-pro-documentation/content/update_notes.html">How to update?</a></p>
    <?php endif; ?>

    <?php
    $_comp = wpdreamsCompatibility::Instance();
    if ($_comp->has_errors()):
        ?>
        <div class="wpdreams-box errorbox">
            <p class='errors'>Possible incompatibility! Please go to the <a href="<?php echo get_admin_url()."admin.php?page=asp_compatibility_settings"; ?>">error check</a> page to see the details and solutions!</p>
        </div>
    <?php endif; ?>

    <div class="wpdreams-box">
        <?php ob_start(); ?>
        <label for="p_asp_post_type">Post type</label>
        <select name="p_asp_post_type">
            <?php foreach($post_types as $post_type): ?>
                <option value="<?php echo $post_type ?>"><?php echo $post_type ?></option>
            <?php endforeach; ?>
        </select>
        <label for="p_asp_blog">Blog</label>
        <select name="p_asp_blog">
            <option value="0" selected>Current</option>
            <?php foreach($blogs as $blog): ?>
                <?php $blog_details = get_blog_details($blog['blog_id']); ?>
                <option value="<?php echo $blog['blog_id'] ?>"><?php echo  $blog_details->blogname; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="p_asp_ordering">Ordering</label>
        <select name="p_asp_ordering">
            <option value="id DESC" selected>ID descending</option>
            <option value="id ASC">ID ascending</option>
            <option value="title DESC">Title descending</option>
            <option value="title ASC">Title ascending</option>
            <option value="priority DESC">Priority descending</option>
            <option value="priority ASC">Priority ascending</option>
        </select>

        <div style="display: inline-block;">
            <label>Filter</label><input name="p_asp_filter" type="text" placeholder="post title here">
        </div>

        <label>Limit</label><input name="p_asp_limit" type="text" style="width: 40px;" value="20">

        <input type='submit' id="p_asp_submit" class='submit' value='Filter'/>
        <?php $_r = ob_get_clean(); ?>


        <div class='wpdreams-slider'>
            <form name='asp_priorities' id="asp_priorities" method='post'>
                <fieldset>
                    <legend>Filter Posts</legend>
                    <?php print $_r; ?>
                </fieldset>
            </form>
        </div>
    </div>
    <div class="wpdreams-box" style="position: relative; float:left;">
        <div id="p_asp_loader"></div>
        <div id="p_asp_results"><p style="text-align:center;">Click the filter to load results!</p></div>
    </div>
    <div id="asp-options-search">
        <a class="wd-accessible-switch" href="#"><?php echo isset($_COOKIE['asp-accessibility']) ? 'DISABLE ACCESSIBILITY' : 'ENABLE ACCESSIBILITY'; ?></a>
    </div>
    <div class="clear"></div>
</div>