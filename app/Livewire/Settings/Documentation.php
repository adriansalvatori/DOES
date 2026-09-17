<?php

namespace App\Livewire\Settings;

use App\Support\AppBehaviorsDocs;
use Livewire\Component;

class Documentation extends Component
{
    public string $search = '';

    public string $activeCategory = 'all';

    public function selectCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function render()
    {
        $categories = AppBehaviorsDocs::getCategories();

        if (! empty($this->search)) {
            $query = mb_strtolower(trim($this->search));
            $filteredCategories = [];

            foreach ($categories as $catKey => $cat) {
                $matchingArticles = [];

                foreach ($cat['articles'] as $article) {
                    $titleMatch = str_contains(mb_strtolower($article['title']), $query);
                    $summaryMatch = str_contains(mb_strtolower($article['summary']), $query);

                    $pointsMatch = false;
                    if (isset($article['points'])) {
                        foreach ($article['points'] as $p) {
                            if (str_contains(mb_strtolower($p), $query)) {
                                $pointsMatch = true;
                                break;
                            }
                        }
                    }

                    $stepsMatch = false;
                    if (isset($article['steps'])) {
                        foreach ($article['steps'] as $s) {
                            if (str_contains(mb_strtolower($s['name']), $query) || str_contains(mb_strtolower($s['desc']), $query)) {
                                $stepsMatch = true;
                                break;
                            }
                        }
                    }

                    if ($titleMatch || $summaryMatch || $pointsMatch || $stepsMatch) {
                        $matchingArticles[] = $article;
                    }
                }

                if (! empty($matchingArticles)) {
                    $catCopy = $cat;
                    $catCopy['articles'] = $matchingArticles;
                    $filteredCategories[$catKey] = $catCopy;
                }
            }

            $categories = $filteredCategories;
        }

        return view('livewire.settings.documentation', [
            'categories' => $categories,
            'allCategories' => AppBehaviorsDocs::getCategories(),
        ])->layout('components.layouts.app', ['title' => __('Guía de Comportamientos')]);
    }
}
