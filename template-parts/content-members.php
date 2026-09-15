<?php

/**
 * Template part for displaying posts
 *
 *
 * @package sieben
 */ ?>


<div class="mainnews-post container pdtb-default">
    <div class="member">
        <div class="member-details">
            <?php the_post_thumbnail('thumbnail', ['class' => 'img-responsive pull-left pdr20']); ?>
            <?php the_title('<h1>', '</h1>');
            the_content();
            trainer_courses_list()
            ?>
        </div>
    </div>
</div>