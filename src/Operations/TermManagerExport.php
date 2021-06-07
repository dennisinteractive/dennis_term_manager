<?php

namespace Drupal\dennis_term_manager\Operations;

use Drupal\Core\Database\Connection;

/**
 * Class TermManagerExport.
 *
 * @package Drupal\dennis_term_manager\Operations
 */
class TermManagerExport implements TermManagerExportInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * TermManagerExport constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function export() {
    // Do the query.
    $query = $this->query();
    $result = $query->execute();

    // Set the file information.
    $delimiter = ',';
    $file_name = 'taxonomy_export_' . date('Y-m-d_H-i-s', strtotime('now')) . '.csv';

    // Add default CSV/TSV headings.
    if (empty($columns)) {
      $columns = $this->getColumns();
    }

    // Open the stream.
    $out = fopen('php://output', 'w');

    // Send correct header to download file.
    header('Content-Disposition: attachment; filename=' . $file_name . ';');

    // Start the file.
    fputcsv($out, $columns, $delimiter, '"');

    // Populate the file.
    while ($row = $result->fetchObject()) {
      $node_count = 0;
      // Add report data to corresponding column.
      $row_data = [];
      foreach ($columns as $column) {
        switch ($column) {
          case 'index_page':
            $value = $this->getIndexPage($row);
            $row_data[] = isset($value) ? $value : '';
            break;

          case 'node_count':
            $node_count = $row->{$column};
            $row_data[] = isset($row->{$column}) ? $row->{$column} : '';
            break;

          case 'node_count_with_children':
            $count = $this->getNodeCountWithChildren($row->tid);
            $value = $node_count + $count;
            $row_data[] = isset($value) ? $value : '';
            break;

          default:
            $row_data[] = isset($row->{$column}) ? $row->{$column} : '';
            break;
        }

      }
      fputcsv($out, $row_data, $delimiter, '"');
    }

    // Close the stream.
    fclose($out);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->connection->select('taxonomy_term_field_data', 't');

    // Term vocabulary and name.
    $query->addField('t', 'vid', 'vocabulary_name');
    $query->addField('t', 'name', 'term_name');
    $query->addField('t', 'tid', 'tid');

    // Join alias.
    $query->leftJoin('path_alias', 'ua', "ua.path = CONCAT('/taxonomy/term/', CAST(t.tid AS CHAR))");
    $query->addField('ua', 'alias', 'path');

    // Get node count for term.
    $query->addExpression(
      '(SELECT COUNT(1) FROM taxonomy_index ti
      LEFT JOIN node n ON n.type != \'gallery\'
      WHERE ti.tid = t.tid AND ti.nid = n.nid)',
        'node_count'
    );

    // Parent information.
    $query->leftJoin('taxonomy_term__parent', 'p', 'p.entity_id = t.tid');
    $query->leftJoin('taxonomy_term_field_data', 'pd', 'pd.tid = p.entity_id');
    $query->addField('pd', 'name', 'parent_term_name');

    // Index page.
    $query->leftJoin('taxonomy_term__field_index_page', 'i', 'i.entity_id = t.tid');
    $query->addField('i', 'field_index_page_value', 'index_page');

    // Primary article.
    $query->leftJoin('taxonomy_term__field_primary_article', 'pa', 'pa.entity_id = t.tid');
    $query->addField('pa', 'field_primary_article_target_id', 'primary_article_nid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getColumns() {
    return [
      'vocabulary_name',
      'term_name',
      'tid',
      'path',
      'parent_term_name',
      'index_page',
      'primary_article_nid',
      'node_count',
      'node_count_with_children'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexPage($row) {
    switch ($row->index_page) {
      case '1':
        $value = 'Y';
        break;

      default:
        $value = 'N';
        break;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeCountWithChildren($tid) {
    $query = $this->connection->select('taxonomy_index', 'ti');
    $query->addField('ti', 'nid', 'nid');
    $query->leftJoin('node', 'n', 'n.type != \'gallery\'');
    $query->leftJoin('taxonomy_term__parent', 'p', 'p.parent_target_id = ' . $tid);
    $query->where('ti.tid = p.entity_id AND ti.nid = n.nid');

    $results = $query->countQuery()->execute();
    return $results->fetchField();
  }

}
