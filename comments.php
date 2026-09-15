<?php
/**
 * The template for displaying Comments.
 */

if (post_password_required()) {
    return;
}

global $post;

if (have_comments() || comments_open()) {
    $comments_title = __("Kommentare", "sieben");

    if ($post->post_type === "courses") {
        $comments_title = __("Rezensionen", "sieben");
    }
    ?>
    <div class="comments_heading"><?= esc_html($comments_title); ?></div>
    <?php
}

// Display list of comments
if (have_comments()): ?>
    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="comment-area">
                <?php
                wp_list_comments([
                    'avatar_size' => 80,
                    'status'      => 'approve',
                    'style'       => 'div',
                    'short_ping'  => true,
                ]);
                the_comments_navigation();
                ?>
            </div>
        </div>
    </div>
<?php endif;

// Display comment form if comments are open
if (comments_open()): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="comments-count">
                <h5><?php comments_number(); ?></h5>
            </div>
        </div>
        <div class="col-md-12 col-sm-12 leave_form">
            <?php
            comment_form([
                'title_reply' => is_singular('courses') ? __('Rezension schreiben', 'sieben') : __('Kommentar schreiben', 'sieben'),
            ]);
            ?>
        </div>
    </div>
<?php endif; ?>