<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2020 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\Formcreator\Exception\ImportFailureException;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginFormcreatorQuestion extends CommonDBChild implements 
PluginFormcreatorExportableInterface,
PluginFormcreatorDuplicatableInterface,
PluginFormcreatorConditionnableInterface
{
   use PluginFormcreatorConditionnable;

   static public $itemtype = PluginFormcreatorSection::class;
   static public $items_id = 'plugin_formcreator_sections_id';

   /** @var PluginFormcreatorFieldInterface|null $field a field describing the question denpending on its field type  */
   private $field = null;

   /**
    * Returns the type name with consideration of plural
    *
    * @param number $nb Number of item(s)
    * @return string Itemtype name
    */
   public static function getTypeName($nb = 0) {
      return _n('Question', 'Questions', $nb, 'formcreator');
   }

   function addMessageOnAddAction() {}
   function addMessageOnUpdateAction() {}
   function addMessageOnDeleteAction() {}
   function addMessageOnPurgeAction() {}

   /**
    * Return the name of the tab for item including forms like the config page
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Config Item)
    * @param  integer    $withtemplate
    *
    * @return String                   Name to be displayed
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      global $DB;

      switch ($item->getType()) {
         case PluginFormcreatorForm::class:
            $number      = 0;
            $found       = $DB->request([
               'SELECT' => ['id'],
               'FROM'   => PluginFormcreatorSection::getTable(),
               'WHERE'  => [
                  'plugin_formcreator_forms_id' => $item->getID()
               ]
            ]);
            $tab_section = [];
            foreach ($found as $section_item) {
               $tab_section[] = $section_item['id'];
            }

            if (!empty($tab_section)) {
               $count = $DB->request([
                  'COUNT' => 'cpt',
                  'FROM'  => self::getTable(),
                  'WHERE' => [
                     'plugin_formcreator_sections_id' => $tab_section
                  ]
               ])->next();
               $number = $count['cpt'];
            }
            return self::createTabEntry(self::getTypeName($number), $number);
      }
      return '';
   }

   /**
    * Display a list of all form sections and questions
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Form Item)
    * @param  integer    $tabnum       Number of the current tab
    * @param  integer    $withtemplate
    *
    * @see CommonDBTM::displayTabContentForItem
    *
    * @return null                     Nothing, just display the list
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch (get_class($item)) {
         case PluginFormcreatorForm::class:
            static::showForForm($item, $withtemplate);
            break;
      }
   }

   /**
    * May be removed when GLPI 9.5 will  be the lowest supported version
    * workaround use if entity in WHERE when using PluginFormcreatorQuestoin::dropdown 
    * (while editing conditions, list of questions is empty + SQL error)
    * @see bug on GLPI #6488, might be related
    */
   // function isEntityAssign() {
   //    return false;
   // }

   public static function showForForm(CommonDBTM $item, $withtemplate = '') {
      $formId = $item->getID();

      echo '<div id="plugin_formcreator_form" class="plugin_formcreator_form_design" data-itemtype="' . PluginFormcreatorForm::class . '" data-id="' . $formId . '">';
      echo '<ol>';
      $sections = (new PluginFormcreatorSection)->getSectionsFromForm($formId);
      foreach ($sections as $section) {
         echo $section->getDesignHtml();
      }

      // add a section
      echo '<li class="plugin_formcreator_section not-sortable">';
      echo '<a href="javascript:plugin_formcreator.showSectionForm(' . $item->getID() . ');">';
      echo '<i class="fas fa-plus"></i>&nbsp;';
      echo __('Add a section', 'formcreator');
      echo '</a>';
      echo '</li>';

      echo '</ol>';
      echo '</div>';
   }

   /**
    * Get the HTML for the question in form designer
    *
    * @return void
    */
   public function getDesignHtml() {
      if ($this->isNewItem()) {
         return '';
      }

      $html = '';

      $questionId = $this->getID();
      $sectionId = $this->fields[PluginFormcreatorSection::getForeignKeyField()];
      $fieldType = 'PluginFormcreator' . ucfirst($this->fields['fieldtype']) . 'Field';
      $field = new $fieldType($this);
      
      $html .= '<div class="grid-stack-item"'
      . ' data-itemtype="' . self::class . '"'
      . ' data-id="'.$questionId.'"'
      . '>';

      $html .= '<div class="grid-stack-item-content">';

      // Question name
      $html .= $field->getHtmlIcon() . '&nbsp;';
      $onclick = 'plugin_formcreator.showQuestionForm(' . $sectionId . ', ' . $questionId . ');';
      $html .= '<a href="javascript:' . $onclick . '" data-field="name">';
      $html .= empty($this->fields['name']) ? '(' . $questionId . ')' : $this->fields['name'];
      $html .= '</a>';

      // Delete the question
      $html .= "<span class='form_control pointer'>";
      $html .= '<i class="far fa-trash-alt"
               onclick="plugin_formcreator.deleteQuestion(this)"></i> ';
      $html .= "</span>";

      // Clone the question
      $html .= "<span class='form_control pointer'>";
      $html .= '<i class="far fa-clone"
               onclick="plugin_formcreator.duplicateQuestion(this)"></i> ';
      $html .= "</span>";

      // Toggle mandatory for the question
      $html .= "<span class='form_control pointer'>";
      $required = ($this->fields['required'] == '0') ? 'far fa-circle' : 'far fa-check-circle';
      $html .= '<i class="' . $required .'"
               onclick="plugin_formcreator.toggleRequired(this)"></i> ';
      $html .= "</span>";

      $html .= '</div>'; // grid stack item content

      $html .= '</div>'; // grid stack item

      return $html;
   }

   public function getRenderedHtml($canEdit = true, $value = []) {
      if ($this->isNewItem()) {
         return '';
      }

      $html = '';

      $field = PluginFormcreatorFields::getFieldInstance(
         $this->fields['fieldtype'],
         $this
      );
      if (!$field->isPrerequisites()) {
         return '';
      }
      if (isset($_SESSION['formcreator']['data']['formcreator_field_' . $this->getID()])) {
         $field->parseAnswerValues($_SESSION['formcreator']['data']['formcreator_field_' . $this->getID()]);
      } else if (count($value) > 0) {
         $field->parseAnswerValues($value);
      } else {
         $field->deserializeValue($this->fields['default_values']);
      }

      $required = ($this->fields['required']) ? ' required' : '';
      $x = $this->fields['col'];
      $width = $this->fields['width'];
      $html .= '<div'
         . ' data-gs-x="' . $x . '"'
         . ' data-gs-width="' . $width . '"'
         . ' data-itemtype="' . self::class . '"'
         . ' data-id="' . $this->getID() . '"'
         . ' >';
      $html .= '<div class="grid-stack-item-content form-group ' . $required . '" id="form-group-field-' . $this->getID() . '">';
      $html .= $field->show($canEdit);
      $html .= '</div>';
      $html .= '</div>';

      return $html;
   }

   /**
    * Validate form fields before add or update a question
    * @param  array $input Datas used to add the item
    * @return array        The modified $input array
    */
   private function checkBeforeSave($input) {
      // Control fields values :
      // - name is required
      if (isset($input['name'])) {
         if (empty($input['name'])) {
            Session::addMessageAfterRedirect(__('The title is required', 'formcreator'), false, ERROR);
            return [];
         }
      }

      // - field type is required
      if (isset($input['fieldtype'])
          && empty($input['fieldtype'])) {
         Session::addMessageAfterRedirect(__('The field type is required', 'formcreator'), false, ERROR);
         return [];
      }

      // - section is required
      if (isset($input['plugin_formcreator_sections_id'])
          && empty($input['plugin_formcreator_sections_id'])) {
         Session::addMessageAfterRedirect(__('The section is required', 'formcreator'), false, ERROR);
         return [];
      }

      if (!isset($input['fieldtype'])) {
         $input['fieldtype'] = $this->fields['fieldtype'];
      }
      $this->field = PluginFormcreatorFields::getFieldInstance(
         $input['fieldtype'],
         $this
      );
      if ($this->field === null) {
         Session::addMessageAfterRedirect(
            // TRANS: $%1$s is a type of field, %2$s is the label of a question
            sprintf(
               __('Field type %1$s is not available for question %2$s.', 'formcreator'),
               $input['fieldtype'],
               $input['name']
               ),
            false,
            ERROR
         );
         return [];
      }
      // - field type is compatible with accessibility of the form
      $section = new PluginFormcreatorSection();
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      if (isset($input[$sectionFk])) {
         $section->getFromDB($input[$sectionFk]);
      } else {
         $section->getFromDB($this->fields[$sectionFk]);
      }
      $form = new PluginFormcreatorForm();
      $form->getFromDBBySection($section);
      if ($form->isPublicAccess() && !$this->field->isAnonymousFormCompatible()) {
         Session::addMessageAfterRedirect(__('This type of question is not compatible with public forms.', 'formcreator'), false, ERROR);
         return [];
      }

      // Check the parameters are provided
      $parameters = $this->field->getEmptyParameters();
      if (count($parameters) > 0) {
         if (!isset($input['_parameters'][$input['fieldtype']])) {
            // This should not happen
            Session::addMessageAfterRedirect(__('This type of question requires parameters', 'formcreator'), false, ERROR);
            return [];
         }
         foreach ($parameters as $parameter) {
            if (!isset($input['_parameters'][$input['fieldtype']][$parameter->getFieldName()])) {
               // This should not happen
               Session::addMessageAfterRedirect(__('A parameter is missing for this question type', 'formcreator'), false, ERROR);
               return [];
            }
         }
      }

      $input = $this->field->prepareQuestionInputForSave($input);
      if ($input === false || !is_array($input)) {
         // Invalid data
         return [];
      }

      // Might need to merge $this->fields and $input, $input having precedence
      // over $this->fields
      $input['default_values'] = $this->field->serializeValue();

      return $input;
   }

   /**
    * Prepare input data for adding the question
    * Check fields values and get the order for the new question
    *
    * @param array $input data used to add the item
    *
    * @return array the modified $input array
    */
   public function prepareInputForAdd($input) {
      if (!isset($input['_skip_checks'])
          || !$input['_skip_checks']) {
         $input = $this->checkBeforeSave($input);
      }
      if (count($input) === 0) {
         return [];
      }

      // Compute default position
      if (!isset($input['col'])) {
         $input['col'] = 0;
      }
      if (!isset($input['width'])) {
         $input['width'] = PluginFormcreatorSection::COLUMNS - $input['col'];
      }
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      // Get next row
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $maxRow = PluginFormcreatorCommon::getMax($this, [
         $sectionFk => $input[$sectionFk]
      ], 'row');
      if ($maxRow === null) {
         $input['row'] = 0;
      } else {
         $input['row'] = $maxRow + 1;
      }
      // if (!isset($input['height'])) {
      //    $input['height'] = '1';
      // }

      // generate a unique id
      if (!isset($input['uuid'])
          || empty($input['uuid'])) {
         $input['uuid'] = plugin_formcreator_getUuid();
      }

      return $input;
   }

   /**
    * Prepare input data for adding the question
    * Check fields values and get the order for the new question
    *
    * @param array $input data used to add the item
    *
    * @array return the modified $input array
    */
   public function prepareInputForUpdate($input) {
      global $DB;

      if (!isset($input['_skip_checks'])
          || !$input['_skip_checks']) {
         $input = $this->checkBeforeSave($input);
      }

      if (!is_array($input) || count($input) == 0) {
         return false;
      }

      // generate a uSnique id
      if (!isset($input['uuid'])
          || empty($input['uuid'])) {
         if (!isset($this->fields['uuid']) && $this->fields['uuid'] != $input['uuid']) {
            $input['uuid'] = plugin_formcreator_getUuid();
         }
      }

      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      if (isset($input[$sectionFk])) {
         // If change section, reorder questions
         if ($input[$sectionFk] != $this->fields[$sectionFk]) {
            $oldId = $this->fields[$sectionFk];
            $newId = $input[$sectionFk];
            $row = $this->fields['row'];
            // Reorder other questions from the old section
            $DB->update(
               self::getTable(),
               new QueryExpression("`row` = `row` - 1"),
               [
                  'row' => ['>', $row],
                  $sectionFk => $oldId,
               ]
            );

            // Get the order for the new question
            $maxRow = PluginFormcreatorCommon::getMax($this, [
               $sectionFk => $newId
            ], 'row');
            if ($maxRow === null) {
               $input['row'] = 1;
            } else {
               $input['row'] = $maxRow + 1;
            }
         }
      }

      return $input;
   }

   protected function serializeDefaultValue($input) {
      // Might need to merge $this->fields and $input, $input having precedence
      // over $this->fields
      $question = new self();
      $question->fields = $input;
      $field = PluginFormcreatorFields::getFieldInstance(
         $input['fieldtype'],
         $question
      );
      $field->parseDefaultValue($input['default_values']);
      $input['default_values'] = $field->serializeValue();
      return $input;
   }

   /** 
    * Update size or position of the question
    * @param array $input 
    * @return boolean false on error
    */
   public function change($input) {
      $x = $this->fields['col'];
      $y = $this->fields['row'];
      $width = $this->fields['width'];
      $height = 1;

      if (isset($input['x'])) {
         if ($input['x'] < 0) {
            return false;
         }
         if ($input['x'] > PluginFormcreatorSection::COLUMNS - 1) {
            return false;
         }
         $x = $input['x'];
      }

      if (isset($input['y'])) {
         if ($input['y'] < 0) {
            return false;
         }
         $sectionFk = PluginFormcreatorSection::getForeignKeyField();
         $maxRow = 1 + PluginFormcreatorCommon::getMax(
            $this, [
               $sectionFk => $this->fields[$sectionFk]
            ],
            'row'
         );
         if ($input['y'] > $maxRow) {
            return false;
         }
         $y = $input['y'];
      }

      if (isset($input['width'])) {
         if ($input['width'] <= 0) {
            return false;
         }
         if ($input['width'] > (PluginFormcreatorSection::COLUMNS - $x)) {
            return false;
         }
         $width = $input{'width'};
      }

      if (isset($input['height'])) {
         if ($input['height'] <= 0) {
            return false;
         }
         if ($input['height'] > 1) {
            return false;
         }
         $height = $input['height'];
      }

      $success = $this->update([
         'id'     => $this->getID(),
         '_skip_checks' => true,
         'col'      => $x,
         'row'      => $y,
         'width'  => $width,
         'height' => $height,
      ]);

      return $success;
   }

   /**
    * set or reset the required flag
    *
    * @param boolean $isRequired
    */
   public function setRequired($isRequired) {
      $this->update([
         'id'           => $this->getID(),
         'required'     => $isRequired,
         '_skip_checks' => true,
      ]);
   }

   /**
    * Adds or updates parameters of the question
    * @param array $input parameters
    */
   public function updateParameters($input) {
      // The question instance has a field type
      if (!isset($this->fields['fieldtype'])) {
         return;
      }
      $fieldType = $this->fields['fieldtype'];

      // The fieldtype may change
      if (isset($input['fieldtype'])) {
         $fieldType = $input['fieldtype'];
      }

      $this->field = PluginFormcreatorFields::getFieldInstance(
         $fieldType,
         $this
      );
      $this->field->updateParameters($this, $input);
   }

   public function pre_deleteItem() {
      $success = (new PluginFormcreatorCondition())->deleteByCriteria([
         'itemtype' => self::class,
         'items_id' => $this->getID(),
      ]);
      if (!$success) {
         return false;
      }

      $this->field = PluginFormcreatorFields::getFieldInstance(
         $this->fields['fieldtype'],
         $this
      );
      return $this->field->deleteParameters($this);
   }

   public function post_addItem() {
      $this->updateConditions($this->input);
      $this->updateParameters($this->input);
   }

   public function post_updateItem($history = 1) {
      $this->updateConditions($this->input);
      $this->updateParameters($this->input);
   }

   /**
    * Actions done after the PURGE of the item in the database
    * Reorder other questions
    *
    * @return void
    */
   public function post_purgeItem() {
      global $DB;

      $table = self::getTable();
      $condition_table = PluginFormcreatorCondition::getTable();

      // Move up questions under this one, if row is empty
      // TODO: handle multiple consecutive empty rows
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $section = new PluginFormcreatorSection();
      $section->getFromDB($this->fields[$sectionFk]);
      if ($section->isRowEmpty($this->fields['row'])) {
         // Rows of the item are empty
         $row = $this->fields['row'];
         $sectionId = $this->fields[$sectionFk];
         $DB->query("
            UPDATE `$table`
            SET `row` = `row` - 1
            WHERE `row` > '$row' AND `$sectionFk` = '$sectionId'
         ");
         // $DB->update(
         //    $table,
         //    new QueryExpression("`row` = `row` - 1"),
         //   [
         //      'row' => ['>', $row],
         //      $sectionFk => $this->fields[$sectionFk]
         //   ]
         // );   
      }

      // Always show questions with conditional display on the question being deleted
      $questionId = $this->fields['id'];
      $DB->update(
         $table,
         [
            'show_rule' => PluginFormcreatorCondition::SHOW_RULE_ALWAYS
         ],
         [
            'id' => new QuerySubquery([
               'SELECT' => self::getForeignKeyField(),
               'FROM' => $condition_table,
               'WHERE' => ['show_field' => $questionId]
            ])
         ]
      );

      $DB->delete(
         $condition_table,
         [
            'OR' => [
               self::getForeignKeyField() => $questionId,
               'show_field' => $questionId
            ]
         ]
      );
   }

   public function showForm($ID, $options = []) {
      if ($ID == 0) {
         $title =  __('Add a question', 'formcreator');
         $action = 'plugin_formcreator.addQuestion()';
      } else {
         $title =  __('Edit a question', 'formcreator');
         $action = 'plugin_formcreator.editQuestion()';
      }

      $rand = mt_rand();
      echo '<form name="form"'
      . ' method="post"'
      . ' action="javascript:' . $action . '"'
      . ' data-itemtype="' . self::class . '"'
      . '>';
      echo '<table class="tab_cadre_fixe">';

      echo '<tr>';
      echo '<th colspan="4">';
      echo $title;
      echo '</th>';
      echo '</tr>';

      echo '<tr>';

      // name
      echo '<td width="20%">';
      echo '<label for="name" id="label_name">';
      echo  __('Title');
      echo '<span style="color:red;">*</span>';
      echo '</label>';
      echo '</td>';

      echo '<td width="30%">';
      echo Html::input('name', [
         'id' => 'name',
         'autofocus' => '',
         'value' => $this->fields['name'],
         'class' => 'required',
      ]);
      echo '</td>';

      // Section
      echo '<td width="20%">';
      echo '<label for="dropdown_plugin_formcreator_sections_id'.$rand.'" id="label_name">';
      echo  _n('Section', 'Sections', 1, 'formcreator');
      echo '<span style="color:red;">*</span>';
      echo '</label>';
      echo '</td>';
      echo '<td width="30%">';
      $section = new PluginFormcreatorSection();
      $section->getFromDB($this->fields['plugin_formcreator_sections_id']);
      $sections = [];
      foreach ((new PluginFormcreatorSection())->getSectionsFromForm($section->fields[PluginFormcreatorForm::getForeignKeyField()]) as $section) {
         $sections[$section->getID()] = $section->getField('name');
      }
      $currentSectionId = ($this->fields['plugin_formcreator_sections_id'])
                        ? $this->fields['plugin_formcreator_sections_id']
                        : (int) $_REQUEST['section_id'];
      Dropdown::showFromArray('plugin_formcreator_sections_id', $sections, [
         'value' => $currentSectionId,
         'rand'  => $rand,
      ]);
      echo '</td>';
      echo '</tr>';

      echo '<tr>';

      // Field type
      echo '<td>';
      echo '<label for="dropdown_fieldtype'.$rand.'" id="label_fieldtype">';
      echo _n('Type', 'Types', 1);
      echo '<span style="color:red;">*</span>';
      echo '</label>';
      echo '</td>';

      echo '<td>';
      $fieldtypes = PluginFormcreatorFields::getNames();
      Dropdown::showFromArray('fieldtype', $fieldtypes, [
         'value'       => $this->fields['fieldtype'],
         'on_change'   => "plugin_formcreator_changeQuestionType($rand)",
         'rand'        => $rand,
      ]);
      echo '</td>';

      echo '<td id="plugin_formcreator_subtype_label">';
      echo '</td>';

      echo '<td id="plugin_formcreator_subtype_value">';
      echo '</td>';
      echo '</tr>';

      echo '<tr>';
      // required
      echo '<td>';
      echo '<label for="dropdown_required'.$rand.'" id="label_required">';
      echo __('Required', 'formcreator');
      echo '</label>';
      echo '</td>';

      echo '<td id="plugin_formcreator_required">';
      dropdown::showYesNo('required', $this->fields['required'], -1, [
         'rand'  => $rand,
      ]);
      echo '</td>';

      // show empty
      echo '<td>';
      echo '<label for="dropdown_show_empty'.$rand.'" id="label_show_empty">';
      echo __('Show empty', 'formcreator');
      echo '</label>';
      echo '</td>';

      echo '<td id="plugin_formcreator_show_empty">';
      dropdown::showYesNo('show_empty', $this->fields['show_empty'], -1, [
         'rand'  => $rand,
      ]);
      echo '</td>';
      echo '</tr>';

      // Empty row for question-specific settings
      // To be replaced bydynamically
      echo '<tr class="plugin_formcreator_question_specific">';
      echo '<td></td><td></td><td></td><td></td>';
      echo '</tr>';

      echo '<tr id="description_tr">';
      // Description of the question
      echo '<td>';
      echo '<label for="description" id="label_description">';
      echo __('Description');
      echo '</label>';
      echo '</td>';

      echo '<td width="80%" colspan="3">';
      echo Html::textarea([
         'name'    => 'description',
         'id'      => 'description',
         'value'   => $this->fields['description'],
         'enable_richtext' => true,
         'display' => false,
      ]);
      echo '</td>';
      echo '</tr>';

      // Condiion to show the question
      echo '<tr>';
      echo '<th colspan="4">';
      echo __('Condition to show the question', 'formcreator');
      echo '</label>';
      echo '</th>';
      echo '</tr>';
      $condition = new PluginFormcreatorCondition();
      $condition->showConditionsForItem($this);

      echo '<tr>';
      echo '<td colspan="4" class="center">';
      echo Html::hidden('id', ['value' => $ID]);
      echo Html::hidden('uuid', ['value' => $this->fields['uuid']]);
      echo '</td>';
      echo '</tr>';
      $this->showFormButtons($options + [
         'candel' => false
      ]);
      echo Html::scriptBlock("plugin_formcreator_changeQuestionType($rand)");
      Html::closeForm();
   }

   /**
    * Duplicate a question
    *
    * @return integer|boolean ID of  the new question, false otherwise
    */
   public function duplicate() {
      $linker = new PluginFormcreatorLinker();

      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $export = $this->export(true);
      $newQuestionId = static::import($linker, $export, $this->fields[$sectionFk]);

      if ($newQuestionId === false) {
         return false;
      }
      $linker->linkPostponed();

      return $newQuestionId;
   }

   public static function import(PluginFormcreatorLinker $linker, $input = [], $containerId = 0) {
      global $DB;

      if (!isset($input['uuid']) && !isset($input['id'])) {
         throw new ImportFailureException('UUID or ID is mandatory');
      }

      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $input[$sectionFk] = $containerId;
      $input['_skip_checks'] = true;

      $item = new self();
      // Find an existing question to update, only if an UUID is available
      $itemId = false;
      /** @var string $idKey key to use as ID (id or uuid) */
      $idKey = 'id';
      if (isset($input['uuid'])) {
         $idKey = 'uuid';
         $itemId = plugin_formcreator_getFromDBByField(
            $item,
            'uuid',
            $input['uuid']
         );
      }

      // escape text fields
      foreach (['name', 'description', 'default_values', 'values'] as $key) {
         $input[$key] = $DB->escape($input[$key]);
      }

      // Add or update question
      $originalId = $input[$idKey];
      if ($itemId !== false) {
         $input['id'] = $itemId;
         $item->field = PluginFormcreatorFields::getFieldInstance(
            $input['fieldtype'],
            $item
         );
         $item->update($input);
      } else {
         unset($input['id']);
         $itemId = $item->add($input);
      }
      if ($itemId === false) {
         $typeName = strtolower(self::getTypeName());
         throw new ImportFailureException(sprintf(__('failed to add or update the %1$s %2$s', 'formceator'), $typeName, $input['name']));
      }

      // add the question to the linker
      $linker->addObject($originalId, $item);

      // Import conditions
      if (isset($input['_conditions'])) {
         foreach ($input['_conditions'] as $condition) {
            PluginFormcreatorCondition::import($linker, $condition, $itemId);
         }
         $field = PluginFormcreatorFields::getFieldInstance(
            $input['fieldtype'],
            $item
         );
      }

      // Import parameters
      if (isset($input['_parameters'])) {
         $parameters = $field->getParameters();
         foreach ($parameters as $fieldName => $parameter) {
            $parameter::import($linker, $input['_parameters'][$input['fieldtype']][$fieldName], $itemId);
         }
      }

      return $itemId;
   }

   public function export($remove_uuid = false) {
      if ($this->isNewItem()) {
         return false;
      }

      $question = $this->fields;

      // remove key and fk
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      unset($question[$sectionFk]);

      // get question conditions
      $question['_conditions'] = [];
      $condition = new PluginFormcreatorCondition();
      $all_conditions = $condition->getConditionsFromItem($this);
      foreach ($all_conditions as $condition) {
         $question['_conditions'][] = $condition->export($remove_uuid);
      }

      // get question parameters
      $question['_parameters'] = [];
      $this->field = PluginFormcreatorFields::getFieldInstance($this->fields['fieldtype'], $this);
      $parameters = $this->field->getParameters();
      foreach ($parameters as $fieldname => $parameter) {
         $question['_parameters'][$this->fields['fieldtype']][$fieldname] = $parameter->export($remove_uuid);
      }

      // remove ID or UUID
      $idToRemove = 'id';
      if ($remove_uuid) {
         $idToRemove = 'uuid';
      }
      unset($question[$idToRemove]);

      return $question;
   }

   /**
    * return array of question objects belonging to a form
    * @param integer $formId
    * @param array $crit array for the WHERE clause
    * @return PluginFormcreatorQuestion[]
    */
   public function getQuestionsFromForm($formId, $crit = []) {
      global $DB;

      $table_question = PluginFormcreatorQuestion::getTable();
      $table_section  = PluginFormcreatorSection::getTable();
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $formFk = PluginFormcreatorForm::getForeignKeyField();
      $result = $DB->request([
         'SELECT' => "$table_question.*",
         'FROM' => $table_question,
         'LEFT JOIN' => [
            $table_section => [
               'FKEY' => [
                  $table_question => $sectionFk,
                  $table_section => 'id',
               ],
            ],
         ],
         'WHERE' => [
            'AND' => [$formFk => $formId] + $crit,
         ],
         'ORDER' => [
            "$table_section.order",
            "$table_question.row",
            "$table_question.col",
         ]
      ]);

      $questions = [];
      foreach ($result as $row) {
         $question = new self();
         $question->getFromDB($row['id']);
         $questions[$row['id']] = $question;
      }

      return $questions;
   }

   /**
    * Gets questions belonging to a section
    *
    * @param integer $sectionId
    *
    * @return PluginFormcreatorQuestion[]
    */
   public function getQuestionsFromSection($sectionId) {
      global $DB;

      $questions = [];
      $rows = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => self::getTable(),
         'WHERE'  => [
            'plugin_formcreator_sections_id' => $sectionId
         ],
         'ORDER'  => ['row ASC', 'col ASC']
      ]);
      foreach ($rows as $row) {
            $question = new self();
            $question->getFromDB($row['id']);
            $questions[$row['id']] = $question;
      }

      return $questions;
   }

   /**
    * get questions of a form grouped by section name and filtered by criteria
    *
    * @param integer $formId
    * @param array $crit additional slection criterias criterias
    * @return array 1st level is the section name, 2nd level is id and name of the question
    */
   public function getQuestionsFromFormBySection($formId, $crit = []) {
      global $DB;

      $questionTable = PluginFormcreatorQuestion::getTable();
      $sectionTable  = PluginFormcreatorSection::getTable();
      $sectionFk     = PluginFormcreatorSection::getForeignKeyField();
      $formFk        = PluginFormcreatorForm::getForeignKeyField();
      $result = $DB->request([
         'SELECT' => [
            $questionTable => ['id as qid', 'name as qname'],
            $sectionTable => ['name as sname'],
         ],
         'FROM' => $questionTable,
         'LEFT JOIN' => [
            $sectionTable => [
               'FKEY' => [
                  $questionTable => $sectionFk,
                  $sectionTable => 'id',
               ],
            ],
         ],
         'WHERE' => [
            'AND' => [$formFk => $formId] + $crit,
         ],
         'ORDER' => [
            "$sectionTable.order",
            "$questionTable.row",
            "$questionTable.col",
         ]
      ]);

      $items = [];
      foreach ($result as $question) {
         if (!isset($items[$question['sname']])) {
            $items[$question['sname']] = [];
         }
         $items[$question['sname']][$question['qid']] = $question['qname'];
      }

      return $items;
   }

   public static function dropdownForForm($formId, $crit, $name, $value) {
      $question = new self();
      $items = $question->getQuestionsFromFormBySection($formId, $crit);
      Dropdown::showFromArray($name, $items, []);
   }

   /**
    * Get linked data (conditions, regexes or ranges) for a question
    *
    * @param string    $table   target table containing the needed data (
    *                           condition, range or regex)
    * @param int|array $id      a single id or an array of ids
    * @return array
    */
   public static function getQuestionDataById($table, $id) {
      global $DB;

      $validTargets = [
         \PluginFormcreatorCondition::getTable(),
         \PluginFormcreatorQuestionRegex::getTable(),
         \PluginFormcreatorQuestionRange::getTable(),
      ];

      if (array_search($table, $validTargets) === false) {
         throw new \InvalidArgumentException("Invalid target ('$table')");
      }

      return iterator_to_array($DB->request([
         'FROM' => $table,
         'WHERE' => [
            "plugin_formcreator_questions_id" => $id
         ]
      ]));
   }

   /**
    * Get either:
    *  - questions, conditions, regexes and range of target parent sections
    *  - conditions, regexes and range of target question
    *
    * @param int $parents target parent sections
    * @param int $id target question
    * @return array
    */
   public static function getFullData($parents, $id = null) {
      global $DB;

      $data = [];

      if ($parents) {
         // Load questions
         $data['_questions'] = iterator_to_array($DB->request([
            'FROM' => \PluginFormcreatorQuestion::getTable(),
            'WHERE' => [
               "plugin_formcreator_sections_id" => $parents
            ]
         ]));

         $questionIds = [];
         foreach ($data['_questions'] as $question) {
            $questionIds[] = $question['id'];
         }

         if (!count($questionIds)) {
            $questionIds[] = -1;
         }

         $id = $questionIds;
      }

      if ($id == null) {
         throw new \InvalidArgumentException(
            "Parameter 'id' can't be null if parameter 'parents' is not specified"
         );
      }

      // Load conditions, regexes and ranges
      $data['_conditions'] = self::getQuestionDataById(
         \PluginFormcreatorCondition::getTable(),
         $id
      );
      $data['_regexes'] = self::getQuestionDataById(
         \PluginFormcreatorQuestionRegex::getTable(),
         $id
      );
      $data['_ranges'] = self::getQuestionDataById(
         \PluginFormcreatorQuestionRange::getTable(),
         $id
      );

      // Load ip, may be needed for some questions
      $data['_ip'] = \Toolbox::getRemoteIpAddress();

      return $data;
   }

   public function post_getFromDB() {
      // Set additional data for the API
      if (isAPI()) {
         $this->fields += self::getFullData(null, $this->fields['id']);
      }
   }
}
