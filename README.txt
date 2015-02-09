Module:  dennis_term_manager

Description
===========
Tool to bulk manage taxonomy terms

Installation
============
Copy the module directory in to your Drupal:
/sites/all/modules directory as usual.

Configuration
=============
Visit admin/structure/taxonomy/term_manager
or via menus Structure > Taxonomy > Term Manager

Upload a CSV/TSV file and click import. A drupal queue
will be created for each CSV row and run with system cron.

Documentation
=============
This Module is designed to Manage various actions that can be performed on Taxonomy terms.
Various actions include Create/Delete/Merge/Move Parent Taxonomy terms.
Taxonomy terms are uploaded in CSV/TSV formats including their actions. This module will then process the CSV/TSV
and create drupal queue namely 'dennis_term_mangaer_queue' and create queue item for each row in CSV. This queue then run with drupal cron. 
Action "Merge Term" will create queue item for each node that reference the merging term.
First line of the file must contain a header row. Format for CSV column can be found in UI. URL for this is "admin/structure/taxonomy/term_manager"
A text with the same name as CSV will be created for error reporting, which can be accessed via Admin UI under report, "admin/structure/taxonomy/term_manager".
This file records any error occurred during processing individual row in drupal queue.

Following are the assumptions made when developing this module:
1) In case of term merge, URL Alias will not be changed when updating referenced nodes.
2) In case of term merge, If merging (target) term does have children, Then those children will be moved as child of source term.
3) In case of move parent, only immediate parent will be changed. Rest of the hierarchy would remain the same.
4) A term will not be deleted if there are nodes referencing to it.
5) A duplicate term will not be created.
6) Taxonomy Terms can only be moved and merged within same vocabulary.
7) Column "target" must be in described format. e.g vocabulary_name >-> Term_name
8) Taxonomy term url will be updated in case of rename.
9) Dennis term manager queue items will be processed for 60 second in one cron run.

Examples
========

| vocabulary_name | term_name    | parent_term | action      | target               | new_name | description                                        |
| Category        | Android      | Server      | move parent |                      |          | Make "Android" child of "Mobile"                   |
| Category        | Android      |             | move parent |                      |          | Make "Android" a root term                         |
| Category        | Android      |             | rename      |                      | Robot    | Rename "Android" to "Robot"                        |
| Category        | Teleporter   |             | create      |                      |          | Create new term "Teleporter"                       |
| Category        | Teleporter   | Mobile      | create      |                      |          | Create new term "Teleporter" with parent "Mobile"  |
| Category        | Android      |             | delete      |                      |          | Delete "Android"                                   |
| Category        | Apps         |             | merge       | Category >-> Android |          | Merge "Android" content into "Apps"                |
