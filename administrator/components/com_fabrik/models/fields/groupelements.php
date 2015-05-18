<?php
/**
 * Renders a grouped list of fabrik groups and elements
 *
 * @package     Joomla
 * @subpackage  Form
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

JFormHelper::loadFieldClass('groupedlist');

/**
 * Renders a list of fabrik lists or db tables
 *
 * @package     Fabrik
 * @subpackage  Form
 * @since       3.1
 */
class JFormFieldGroupElements extends JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 */
	public $type = 'GroupElements';

	/**
	 * Method to get the list of groups and elements
	 * grouped by group and element.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 */
	protected function getGroups()
	{
		$model = $this->form->model;

		$rows = array();
		$rows[FText::_('COM_FABRIK_GROUPS')] = array();
		$rows[FText::_('COM_FABRIK_ELEMENTS')] = array();

		// Get available element types
		$groups = $model->getFormModel()->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$group = $groupModel->getGroup();
			$label = $group->get('name');
			$value = 'fabrik_trigger_group_group' . $group->get('id');
			$rows[FText::_('COM_FABRIK_GROUPS')][] = JHTML::_('select.option', $value, $label);
			$elementModels = $groupModel->getMyElements();

			foreach ($elementModels as $elementModel)
			{
				$label = $elementModel->getFullName(false, false);
				$value = 'fabrik_trigger_element_' . $elementModel->getFullName(true, false);
				$rows[FText::_('COM_FABRIK_ELEMENTS')][] = JHTML::_('select.option', $value, $label);
			}
		}

		reset($rows);
		asort($rows[FText::_('COM_FABRIK_ELEMENTS')]);

		return $rows;
	}
}
