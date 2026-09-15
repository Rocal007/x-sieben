<?php
class CourseController {
    protected CourseRepository $repo;

    public function __construct(CourseRepository $repo) {
        $this->repo = $repo;
    }

    // public function loadCourses(string $layout): string {
    //     $courses = $this->repo->getCourses();
    //     return CourseRenderer::render($courses, $layout); // now always returns string
    // }
}

