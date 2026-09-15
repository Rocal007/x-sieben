<?php 

add_action('rest_api_init', function(){
    register_rest_route('courses/v1', '/all', [
        'methods' => 'GET',
        'callback' => function() {
            $repo = new CourseRepository();
            $courses = $repo->getCourses();
            return array_map(fn($c)=>[
                'title' => $c->getTitle(),
                'subtitle' => $c->untertitel,
                'start_date' => $c->start_datum,
                'end_date' => $c->end_datum,
                'permalink' => $c->getPermalink(),
                'image' => $c->getFeaturedImage()
            ], $courses);
        }
    ]);
});
