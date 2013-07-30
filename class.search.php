<?php
    /*
     * A standalone PHP class to perform a search on an InnoDB table
     * --
     * Script currently relies on PDO although it will be re-worked soon for standard MySQL
     *
     *
     * https://github.com/0x6C77/InnoDB-PHP-Search by @0x6C77
     */
    class search {
        private $db; // PDO connection

        private $disallowed_words = array('the', 'and', 'you', 'was', 'for', 'are', 'with', 'they',
            'this', 'have', 'from', 'one', 'had', 'word', 'what', 'were', 'when', 'your', 'said', 'there',
            'each', 'which', 'their', 'will', 'other', 'about', 'many', 'then', 'them', 'some', 'would',
            'make', 'like', 'time', 'look', 'more', 'write', 'number', 'way', 'could', 'people', 'than',
            'first', 'been', 'call', 'who', 'oil', 'its', 'now', 'find', 'long', 'down', 'day', 'did',
            'get', 'come', 'made', 'may', 'part');

        public function searchArticles($q) {
            // Build search query
            $search_query = '';
            preg_match_all('/(?<!")\b\w+\b|(?<=")\b[^"]+/', $q, $result, PREG_PATTERN_ORDER);
            if (!count($result[0]))
                return false;

            for ($i = 0; $i < count($result[0]); $i++) {
                $term = $result[0][$i];
                
                //Check if word is valid
                if (strlen($term) <= 2 || in_array($term, $this->disallowed_words))
                    continue;
                
                $search_query .= "(( LENGTH(title) - LENGTH(REPLACE(LOWER(title), :{$i}, '')) )
                   / CHAR_LENGTH(:{$i}))
                 + (( LENGTH(body) - LENGTH(REPLACE(LOWER(body), :{$i}, '')) )
                   / CHAR_LENGTH(:{$i}))
                 + ";
                $search_values[":$i"] = $term;
            }
            $search_query = substr($search_query, 0, -2);
            if (!strlen($search_query))
                return false;

            $sql = "SELECT SQL_CALC_FOUND_ROWS a.article_id AS id, users.username, a.title, a.slug, a.thumbnail,
                        submitted, updated, a.category_id AS cat_id, categories.title AS cat_title, categories.slug AS cat_slug,
                        CONCAT(IF(a.category_id = 0, '/news/', '/articles/'), a.slug) AS uri,
                        search.matches
                    FROM articles a
                    LEFT JOIN articles_categories categories
                    ON a.category_id = categories.category_id
                    LEFT JOIN users
                    ON users.user_id = a.user_id
                    INNER JOIN (
                         SELECT
                           article_id,
                           SUM(
                             {$search_query}
                           ) AS matches
                         FROM `articles` 
                         GROUP BY `article_id`
                         HAVING matches > 0
                       ) search
                    ON search.article_id = a.article_id
                    GROUP BY a.article_id
                    ORDER BY search.`matches` DESC";

            $st = $this->db->prepare($sql);
            $st->execute($search_values);
            $result = $st->fetchAll();

            if (!count($result))
                return false;

            return $result;
        }
    }
?>