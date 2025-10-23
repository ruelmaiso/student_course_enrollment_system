<?php
/**
 * Pagination Utility Functions
 */

function getPaginationParams($page_param = 'page', $per_page_param = 'per_page') {
    $page = max(1, (int)($_GET[$page_param] ?? 1));
    $per_page = max(5, min(100, (int)($_GET[$per_page_param] ?? 10)));
    $offset = ($page - 1) * $per_page;
    
    return [
        'page' => $page,
        'per_page' => $per_page,
        'offset' => $offset
    ];
}

function getTotalPages($total_records, $per_page) {
    return max(1, ceil($total_records / $per_page));
}

function generatePaginationLinks($current_page, $total_pages, $base_url, $params = []) {
    $links = [];
    
    // Previous page
    if ($current_page > 1) {
        $prev_params = array_merge($params, ['page' => $current_page - 1]);
        $links[] = [
            'url' => $base_url . '?' . http_build_query($prev_params),
            'text' => '&laquo; Previous',
            'class' => 'pagination-link',
            'disabled' => false
        ];
    } else {
        $links[] = [
            'url' => '#',
            'text' => '&laquo; Previous',
            'class' => 'pagination-link disabled',
            'disabled' => true
        ];
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $first_params = array_merge($params, ['page' => 1]);
        $links[] = [
            'url' => $base_url . '?' . http_build_query($first_params),
            'text' => '1',
            'class' => 'pagination-link',
            'disabled' => false
        ];
        
        if ($start_page > 2) {
            $links[] = [
                'url' => '#',
                'text' => '...',
                'class' => 'pagination-link disabled',
                'disabled' => true
            ];
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $page_params = array_merge($params, ['page' => $i]);
        $links[] = [
            'url' => $i == $current_page ? '#' : $base_url . '?' . http_build_query($page_params),
            'text' => (string)$i,
            'class' => $i == $current_page ? 'pagination-link active' : 'pagination-link',
            'disabled' => $i == $current_page
        ];
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $links[] = [
                'url' => '#',
                'text' => '...',
                'class' => 'pagination-link disabled',
                'disabled' => true
            ];
        }
        
        $last_params = array_merge($params, ['page' => $total_pages]);
        $links[] = [
            'url' => $base_url . '?' . http_build_query($last_params),
            'text' => (string)$total_pages,
            'class' => 'pagination-link',
            'disabled' => false
        ];
    }
    
    // Next page
    if ($current_page < $total_pages) {
        $next_params = array_merge($params, ['page' => $current_page + 1]);
        $links[] = [
            'url' => $base_url . '?' . http_build_query($next_params),
            'text' => 'Next &raquo;',
            'class' => 'pagination-link',
            'disabled' => false
        ];
    } else {
        $links[] = [
            'url' => '#',
            'text' => 'Next &raquo;',
            'class' => 'pagination-link disabled',
            'disabled' => true
        ];
    }
    
    return $links;
}

function renderPagination($links) {
    echo '<div class="pagination">';
    foreach ($links as $link) {
        if ($link['disabled']) {
            echo '<span class="' . $link['class'] . '">' . $link['text'] . '</span>';
        } else {
            echo '<a href="' . $link['url'] . '" class="' . $link['class'] . '">' . $link['text'] . '</a>';
        }
    }
    echo '</div>';
}

function getSearchTerm() {
    return trim($_GET['search'] ?? '');
}

function addSearchToParams($params, $search_term) {
    if (!empty($search_term)) {
        $params['search'] = $search_term;
    }
    return $params;
}
?>