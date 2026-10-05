<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;

class GuideController extends Controller
{
    /**
     * The user guide (resources/guide/en.md), rendered on the server. HTML in the file is escaped.
     */
    public function __invoke(): Response
    {
        $html = Str::markdown((string) file_get_contents(resource_path('guide/en.md')), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['symbol' => '#', 'id_prefix' => '', 'fragment_prefix' => '', 'insert' => 'after', 'min_heading_level' => 2, 'max_heading_level' => 3, 'aria_hidden' => true, 'title' => ''],
            'table_of_contents' => ['position' => 'placeholder', 'placeholder' => '[TOC]', 'min_heading_level' => 2, 'max_heading_level' => 2, 'html_class' => 'guide-toc'],
        ], [new HeadingPermalinkExtension, new TableOfContentsExtension]);

        return Inertia::render('guide', ['html' => $html]);
    }
}
