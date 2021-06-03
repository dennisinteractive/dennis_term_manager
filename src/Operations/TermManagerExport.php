<?php

namespace Drupal\dennis_term_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class TermManagerExport extends ControllerBase implements ContainerInjectionInterface {

  protected $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  public function build($delimiter = "\t", $vocabs = [], $columns = []) {
    $query = $this->query($vocabs);
    $result = $query->execute();

    // Send correct header to download file.
    $extension = $delimiter == "\t" ? 'tsv' : 'csv';
    $file_name = 'taxonomy_export_' . date('Y-m-d_H-i-s', REQUEST_TIME) . '.' . $extension;

    // Add default CSV/TSV headings.
    if (empty($columns)) {
      $columns = $this->getColumns();
    }

    $out = fopen('php://output', 'w');

    fputcsv($out, $columns, $delimiter, '"');

    while ($row = $result->fetchObject()) {
      // Add report data to corresponding column.
      $row_data = [];
      foreach ($columns as $column) {
        $row_data[] = isset($row->{$column}) ? $row->{$column} : '';
      }
      fputcsv($out, $row_data, $delimiter, '"');
    }

    $csv_data = stream_get_contents($out);

    fclose($out);

    $response = new Response();
    $response->headers->set('Content-Type', 'text/' . $extension . '; utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename=' . $file_name);
    $response->setContent($csv_data);

    return $response;
  }

  protected function query($vocabs) {
    $query = $this->connection->select('taxonomy_term_field_data', 't');

    // Term vocabulary and name.
    $query->addField('t', 'vid', 'vocabulary_name');
    $query->addField('t', 'name', 'term_name');
    $query->addField('t', 'tid', 'tid');

    // Filter by vocabulary
//    $machine_names = array();
//    if (!empty($vocabs)) {
//      foreach ($vocabs as $name) {
//        $machine_names[] = _dennis_term_manager_machine_name($name);
//      }
//      $query->condition('v.machine_name', $machine_names, 'IN');
//    }

    // Join alias.
    $query->leftJoin('url_alias', 'ua', "ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))");
    $query->addField('ua', 'alias', 'path');


    // Get node count for term.
//    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_index i WHERE i.tid = t.tid)', 'node_count');

    // Get child term count for term.
//    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_term_hierarchy h WHERE h.parent = t.tid)', 'term_child_count');

    // Parent term name.
//    $query->addExpression('IF(p.name IS NULL, \'\', p.name)', 'parent_term_name');

    // Parent information.
    $query->leftJoin('taxonomy_term__parent', 'p', 'p.entity_id = t.tid');
    $query->leftJoin('taxonomy_term_field_data', 'pd', 'pd.tid = p.entity_id');
    $query->addField('pd', 'name', 'parent_term_name');

    // Index page.
    $query->leftJoin('taxonomy_term__field_index_page', 'i', 'i.entity_id = t.tid');
    $query->addField('i', 'field_index_page_value', 'index_page');

    // Primary article
    $query->leftJoin('taxonomy_term__field_primary_article', 'pa', 'pa.entity_id = t.tid');
    $query->addField('pa', 'field_primary_article_target_id', 'primary_article_nid');

    // Group by tid to get node counts for each term.
//    $query->groupBy('t.tid');

    return $query;
  }

  /**
   * CSV/TSV files should always have these columns.
   */
  protected function getColumns() {
    return [
      'vocabulary_name',
      'term_name',
      'tid',
      'path',
      'parent_term_name',
      'index_page',
      'primary_article_nid',
    ];
  }

}
