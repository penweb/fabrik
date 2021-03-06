<?php
/**
 * Form Comment
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.comment
 * @copyright   Copyright (C) 2005-2013 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-form.php';

/**
 * Comment J Table
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       3.0
 */

class FabrikTableComment extends FabTable
{
	/**
	 * Object constructor to set table and key fields.
	 *
	 * @param   JDatabase  &$db  JDatabase connector object.
	 */

	public function __construct(&$db)
	{
		parent::__construct('#__{package}_comments', 'id', $db);
	}
}

/**
 * Insert a comment plugin into the bottom of the form
 * Various different plugin systems supported
 *  * Internal
 *  * disqus
 *  * Intensedebate
 *  * JComments
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.comment
 * @since       3.0
 */

class PlgFabrik_FormComment extends PlgFabrik_Form
{
	/**
	 * HTML comment form
	 *
	 * @var string
	 */
	protected $commentform = null;

	/**
	 * Comments locked
	 *
	 * @var bool
	 */
	protected $commentsLocked = null;

	/**
	 * Data
	 *
	 * @var array
	 */
	protected $data = array();

	protected $thumb = null;

	/**
	 * Get any html that needs to be written after the form close tag
	 *
	 * @return	string	html
	 */

	public function getEndContent_result()
	{
		return $this->data;
	}

	/**
	 * Determine if you can add new comments
	 *
	 * @return  bool
	 */

	protected function commentsLocked()
	{
		$params = $this->getParams();
		$formModel = $this->getModel();

		if (is_null($this->commentsLocked))
		{
			$this->commentsLocked = false;
			$lock = trim($params->get('comment_lock_element'));

			if ($lock !== '')
			{
				$lock = str_replace('.', '___', $lock) . '_raw';
				$lockval = $formModel->data[$lock];

				if ($lockval == 1)
				{
					$this->commentsLocked = true;
				}
			}
		}

		return $this->commentsLocked;
	}

	/**
	 * Sets up any end html (after form close tag)
	 *
	 * @return  void
	 */

	public function getEndContent()
	{
		$formModel = $this->getModel();
		$rowid = $formModel->getRowId();

		if ($rowid == '')
		{
			return;
		}

		$params = $this->getParams();
		$this->commentsLocked();
		$method = $params->get('comment_method', 'disqus');

		switch ($method)
		{
			default:
			case 'disqus':
				$this->_disqus();
				break;
			case 'intensedebate':
				$this->_intensedebate();
				break;
			case 'internal':
				$this->_internal();
				break;
			case 'jcomment':
				$this->_jcomment();
				break;
		}

		return true;
	}

	/**
	 * Get the js options for the thumb element
	 *
	 * @return  string  json option string
	 */

	protected function loadThumbJsOpts()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$opts = new stdClass;
		$thumb = $this->getThumb();
		$opts->livesite = COM_FABRIK_LIVESITE;
		$opts->row_id = $input->getString('rowid', '', 'string');
		$opts->voteType = 'comment';

		FabrikHelperHTML::addPath(COM_FABRIK_BASE . 'plugins/fabrik_element/thumbs/images/', 'image', 'form', false);
		$opts->formid = $this->formModel->getId();
		$opts->j3 = FabrikWorker::j3();
		$opts->listid = $this->formModel->getListModel()->getTable()->id;
		$opts = json_encode($opts);

		return $opts;
	}

	/**
	 * Prepare local comment system
	 *
	 * @return  void
	 */

	protected function _internal()
	{
		$params = $this->getParams();
		$formModel = $this->getModel();
		$app = JFactory::getApplication();
		$input = $app->input;
		$this->inJDb = $formModel->getTableModel()->inJDb();
		$this->formModel = $formModel;
		$jsfiles = array();
		JHTML::stylesheet('/plugins/fabrik_form/comment/comments.css');
		$jsfiles[] = 'media/com_fabrik/js/fabrik.js';
		$jsfiles[] = 'plugins/fabrik_form/comment/comments.js';
		$jsfiles[] = 'plugins/fabrik_form/comment/inlineedit.js';

		$thumbopts = $this->doThumbs() ? $thumbopts = $this->loadThumbJsOpts() : "{}";
		$user = JFactory::getUser();
		$rowid = $input->get('rowid', '', 'string');

		if (strstr($rowid, ':'))
		{
			// SLUG
			$rowid = array_shift(explode(':', $rowid));
		}

		$comments = $this->getComments($formModel->get('id'), $rowid);

		$layout = $this->getLayout('layout');
		$layoutData = new stdClass;
		$layoutData->commentCount = count($comments);
		$layoutData->showCountInTitle = $params->get('comment-show-count-in-title');
		$layoutData->commnents = $this->writeComments($params, $comments);
		$layoutData->commentsLocked = $this->commentsLocked;
		$layoutData->anonymous = $params->get('comment-internal-anonymous');
		$layoutData->userLoggedIn = $user->get('id') == 0;
		$layoutData->form = $this->getAddCommentForm(0, true);

		if ($this->doThumbs())
		{
			$thumb = $this->getThumb();
			$this->thumbCounts = $thumb->getListThumbsCount();
		}

		$opts = new stdClass;
		$opts->formid = $formModel->get('id');
		$opts->rowid = $rowid;
		$opts->admin = $user->authorise('core.delete', 'com_fabrik');
		$opts->label = '';

		foreach ($formModel->data as $k => $v)
		{
			if (strstr($k, 'title'))
			{
				$opts->label = $v;
				break;
			}
		}

		$opts = json_encode($opts);
		JText::script('PLG_FORM_COMMENT_TYPE_A_COMMENT_HERE');
		JText::script('PLG_FORM_COMMENT_PLEASE_ENTER_A_COMMENT_BEFORE_POSTING');
		JText::script('PLG_FORM_COMMENT_PLEASE_ENTER_A_NAME_BEFORE_POSTING');
		JText::script('PLG_FORM_COMMENT_ENTER_EMAIL_BEFORE_POSTNG');
		$script = "var comments = new FabrikComment('fabrik-comments', $opts);";

		if ($this->doThumbs())
		{
			$jsfiles[] = 'plugins/fabrik_element/thumbs/list-thumbs.js';
			$script .= "\n comments.thumbs = new FbThumbsList(" . $this->formModel->getId() . ", $thumbopts);";
		}

		FabrikHelperHTML::script($jsfiles, $script);

		$this->data = $layout->render($layoutData);
	}

	/**
	 * Can we add internal comments
	 *
	 * @return boolean
	 */
	private function canAddComment()
	{
		$user = JFactory::getUser();
		$params = $this->getParams();
		$anonymous = $params->get('comment-internal-anonymous');

		return $user->get('id') == 0 && $anonymous == 0 ? false : true;
	}

	/**
	 * Build the html for the internal comment form
	 *
	 * @param   int   $reply_to  Comment id that we are replying to
	 * @param   bool  $master    Is it the master comment
	 *
	 * @return  string
	 */

	private function getAddCommentForm($reply_to = 0, $master = false)
	{
		$params = $this->getParams();
		$input = JFactory::getApplication()->input;
		$user = JFactory::getUser();

		if (!$this->canAddComment())
		{
			return;
		}

		$layout = $this->getLayout('form');
		$layoutData = new stdClass;
		$layoutData->formId = $master ? " id='master-comment-form' " : '';
		$layoutData->rating = $params->get('comment-internal-rating');
		$layoutData->anonymous = $params->get('comment-internal-anonymous');
		$layoutData->replyTo = $reply_to;
		$layoutData->notify = $params->get('comment_allow_user_subscriptions_to_notifications') == 1;
		$layoutData->name = trim($input->get('ide_people___voornaam', '', 'cookie') . ' ' . $input->get('ide_people___achternaam', '', 'cookie'));
		$layoutData->email = $input->get('ide_people___email', '', 'cookie');
		$layoutData->renderOrder = $this->renderOrder;
		$layoutData->userLoggedIn = $user->get('id') != 0;

		return $layout->render($layoutData);
	}

	/**
	 * TODO replace parentid with left/right markers
	 * see http://dev.mysql.com/tech-resources/articles/hierarchical-data.html
	 * Get the comments from the db
	 *
	 * @param   int     $formid  Form id
	 * @param   string  $rowid   Row id
	 *
	 * @return  array	replies
	 */

	protected function getComments($formid, $rowid)
	{
		$formid = (int) $formid;
		$db = FabrikWorker::getDbo();
		$formModel = $this->setFormModel();
		$query = $db->getQuery(true);
		$query->select('c.*');
		$query->from('#__{package}_comments AS c');
		$this->inJDb = $formModel->getTableModel()->inJDb();

		if ($this->inJDb)
		{
			$query->join('LEFT', '#__users AS u ON c.user_id = u.id');
		}

		$query->where('formid = ' . $formid . ' AND c.row_id = ' . $db->quote($rowid) . ' AND c.approved = 1')->order('c.time_date ASC');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		$main = array();
		$replies = array();

		if (!is_array($rows))
		{
			return array();
		}

		foreach ($rows as $row)
		{
			if ($row->reply_to == 0)
			{
				$main[$row->id] = $row;
			}
			else
			{
				if (!array_key_exists($row->reply_to, $replies))
				{
					$replies[$row->reply_to] = array();
				}

				$replies[$row->reply_to][] = $row;
			}
		}

		$return = array();

		foreach ($main as $v)
		{
			$depth = 0;
			$v->depth = $depth;
			$return[$v->id] = $v;
			$this->getReplies($v, $replies, $return, $depth);
		}

		return $return;
	}

	/**
	 * Recursive method to append the replies to the comments
	 *
	 * @param   object  $v        current comment
	 * @param   array   $replies  replies
	 * @param   array   &$return  return data
	 * @param   int     $depth    current depth
	 *
	 * @return void
	 */

	private function getReplies($v, $replies, &$return, $depth)
	{
		$depth++;

		if (array_key_exists($v->id, $replies) && is_array($replies[$v->id]))
		{
			foreach ($replies[$v->id] as $row)
			{
				$row->depth = $depth;
				$return[$row->id] = $row;
				$this->getReplies($row, $replies, $return, $depth);
			}
		}
	}

	/**
	 * Generate the html for the comments
	 *
	 * @param   object  $params    plugin params
	 * @param   array   $comments  comments to write out
	 *
	 * @return  string
	 */

	private function writeComments($params, $comments)
	{
		$layout = $this->getLayout('comments');
		$layoutData = new stdClass;

		foreach ($comments as &$comment)
		{
			$comment->data = $this->writeComment($params, $comment);
		}

		$layoutData->comments = $comments;

		return $layout->render($layoutData);
	}

	/**
	 * Write a single comment
	 *
	 * @param   object  $params   plugin params
	 * @param   object  $comment  comment to write
	 *
	 * @return  string
	 */

	private function writeComment($params, $comment)
	{
		FabrikHelperHTML::addPath(COM_FABRIK_BASE . 'plugins/fabrik_form/comment/images/', 'image', 'form', false);
		$input = JFactory::getApplication()->input;
		$user = JFactory::getUser();
		$layoutData = new stdClass;
		$layoutData->insrc = FabrikHelperHTML::image("star_in.png", 'form', @$this->tmpl, array(), true);
		$layoutData->name = (int) $comment->annonymous == 0 ? $comment->name : FText::_('PLG_FORM_COMMENT_ANONYMOUS_SHORT');
		$layoutData->comment = $comment;
		$layoutData->dateFormat = $params->get('comment-date-format');
		$layoutData->internalRating = $params->get('comment-internal-rating') == 1;
		$layoutData->canDelete = $user->authorise('core.delete', 'com_fabrik');
		$layoutData->canAdd = !$this->commentsLocked && $this->canAddComment();
		$layoutData->commentsLocked = $this->commentsLocked;
		$layoutData->form = $this->getAddCommentForm($comment->id);
		$layoutData->j3 = FabrikWorker::j3();

		if ($this->doThumbs())
		{
			$layoutData->useThumbsPlugin = true;

			$thumb = $this->getThumb();
			$input->set('commentId', $comment->id);
			$layoutData->thumbs = $thumb->render(array());
		}
		else
		{
			$layoutData->useThumbsPlugin = false;
		}

		$layout = $this->getLayout('comment');

		return $layout->render($layoutData);
	}

	/**
	 * Get list id
	 *
	 * @return  int  list id
	 */

	protected function getListId()
	{
		return $this->getModel()->getListModel()->getId();
	}

	/**
	 * Get thumb element
	 *
	 * @return  object	Thumb element
	 */

	protected function getThumb()
	{
		if (!isset($this->thumb))
		{
			$this->thumb = FabrikWorker::getPluginManager()->getPlugIn('thumbs', 'element');
			$this->thumb->setEditable(true);
			$this->thumb->commentThumb = true;
			$this->thumb->formid = $this->getModel()->getId();
			$this->thumb->listid = $this->getListId();
			$this->thumb->special = 'comments_' . $this->thumb->formid;
		}

		return $this->thumb;
	}

	/**
	 * Delete a comment called from ajax request
	 *
	 * @return  void
	 */

	public function onDeleteComment()
	{
		$db = FabrikWorker::getDbo();
		$app = JFactory::getApplication();
		$id = $app->input->getInt('comment_id');
		$query = $db->getQuery(true);
		$query->delete('#__{package}_comments')->where('id =' . $id);
		$db->setQuery($query);
		$db->execute();
		echo $id;
	}

	/**
	 * Update a comment called from ajax request by admin
	 *
	 * @return  void
	 */

	public function onUpdateComment()
	{
		$db = FabrikWorker::getDbo();
		$app = JFactory::getApplication();
		$input = $app->input;
		$id = $input->getInt('comment_id');
		$comment = $db->quote($input->get('comment', '', 'string'));
		$query = $db->getQuery(true);
		$query->update('#__{package}_comments')->set('comment = ' . $comment)->where('id = ' . $id);
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Set the form model
	 *
	 * @return  object form model
	 */

	private function setFormModel()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$formModel = JModelLegacy::getInstance('form', 'FabrikFEModel');
		$formModel->setId($input->getInt('formid'));
		$this->model = $formModel;

		return $this->model;
	}

	/**
	 * Add a comment called from ajax request
	 *
	 * @return  void
	 */

	public function onAddComment()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$user = JFactory::getUser();
		$row = FabTable::getInstance('comment', 'FabrikTable');
		$filter = JFilterInput::getInstance();
		$request = $filter->clean($_REQUEST, 'array');
		$row->bind($request);
		$row->ipaddress = $_SERVER['REMOTE_ADDR'];
		$row->user_id = $user->get('id');
		$row->approved = 1;

		// @TODO this isn't set?
		$row->url = $input->server->get('HTTP_REFERER', '', 'string');
		$rowid = $input->get('rowid', '', 'string');
		$row->formid = $input->getInt('formid');
		$row->row_id = $rowid;

		if ($user->get('id') != 0)
		{
			$row->name = $user->get('name');
			$row->email = $user->get('email');
		}

		// Load up the correct params for the plugin -
		// First load all form params
		$formModel = $this->setFormModel();
		$params = $formModel->getParams();

		$this->renderOrder = (int) $input->get('renderOrder', 0);

		// Then map that data (for correct render order) onto this plugins params
		$params = $this->setParams($params, $this->renderOrder);
		$row->store();

		// $$$ rob 16/10/2012 db queries run when element/plugin selected in admin, so just return false if error now
		$obj = new stdClass;

		// Do this to get the depth of the comment
		$comments = $this->getComments($row->formid, $row->row_id);
		$row = $comments[$row->id];
		$obj->content = $this->writeComment($params, $row);
		$obj->depth = (int) $row->depth;
		$obj->id = $row->id;
		$notificationPlugin = $this->useNotificationPlugin();
		$this->fixTable();

		if ($notificationPlugin)
		{
			$this->addNotificationEvent($row);
		}

		// Do we notify everyone?
		if ($notificationPlugin)
		{
			$this->saveNotificationToPlugin($row, $comments);
		}
		else
		{
			$this->sentNotifications($row, $comments);
		}

		echo json_encode($obj);
	}

	/**
	 * Initial code missed out adding a notify field to the db.
	 * Manually add it in.
	 *
	 * @return void
	 */
	private function fixTable()
	{
		$table = FabTable::getInstance('Comment', 'FabrikTable');
		$columns = $table->getFields();

		if (!array_key_exists('notify', $columns))
		{
			$db = JFactory::getDbo();
			$query = 'ALTER TABLE `#__fabrik_comments` ADD `notify` TINYINT(1) NOT NULL;';
			$db->setQuery($query)
				->execute();

		}
	}

	/**
	 * Add notification event.
	 *
	 * @param   object  $row  Row?
	 *
	 * @return  void
	 */

	protected function addNotificationEvent($row)
	{
		$formModel = $this->getModel();
		$app = JFactory::getApplication();
		$input = $app->input;
		$db = FabrikWorker::getDbo();
		$event = $db->q('COMMENT_ADDED');
		$user = JFactory::getUser();
		$user_id = (int) $user->get('id');
		$rowid = $input->get('rowid', '', 'string');
		$ref = $db->q($formModel->getlistModel()->getTable()->id . '.' . $formModel->get('id') . '.' . $rowid);
		$date = $db->q(JFactory::getDate()->toSql());
		$query = $db->getQuery(true);
		$query->insert('#__{package}_notification_event')
			->set(array('event = ' . $event, 'user_id = ' . $user_id, 'reference = ' . $ref, 'date_time = ' . $date));
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Couldn\'t save fabrik comment notification event: ' + $db->stderr(true), JLog::WARNING, 'fabrik');

			return false;
		}
	}

	/**
	 * Once we've ensured that the notification plugin is installed
	 * subscribe the user to the notification
	 * If comment-notify-admins is on then also subscribe admins to the notification
	 *
	 * @param   object  $row       Row (not used?)
	 * @param   array   $comments  Objects
	 *
	 * @return  void
	 */

	protected function saveNotificationToPlugin($row, $comments)
	{
		$params = $this->getParams();
		$formModel = $this->getModel();
		$app = JFactory::getApplication();
		$input = $app->input;
		$db = FabrikWorker::getDbo();
		$user = JFactory::getUser();
		$user_id = (int) $user->get('id');
		$rowid = $input->get('rowid', '', 'string');
		$label = $db->quote($input->get('label', '', 'string'));
		$ref = $db->quote($formModel->getlistModel()->getTable()->id . '.' . $formModel->get('id') . '.' . $rowid);
		$query = $db->getQuery(true);

		$onlySubscribed = (bool) $params->get('comment_allow_user_subscriptions_to_notifications');
		$shouldSubscribe = $onlySubscribed === false || ($onlySubscribed && (int) $row->notify === 1);

		if ((int) $params->get('comment-internal-notify') == 1 && $shouldSubscribe)
		{
			$query->insert('#__{package}_notification')
				->set(array('reason = ' . $db->q('commentor'), 'user_id = ' . $user_id, 'reference = ' . $ref, 'label = ' . $label));
			$db->setQuery($query);

			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				JLog::add('Couldn\'t save fabrik comment notification: ' + $db->stderr(true), JLog::WARNING, 'fabrik');

				return false;
			}
		}

		if ($params->get('comment-notify-admins') == 1)
		{
			$rows = $this->getAdminInfo();

			foreach ($rows as $row)
			{
				if ($row->id != $user_id)
				{
					$query->clear();
					$fields = array('reason = ' . $db->quote('admin observing a comment'), 'user_id = ' . $row->id, 'reference = ' . $ref,
						'label = ' . $label);
					$query->insert('#__{package}_notification')->set($fields);
					$db->setQuery($query);

					try
					{
						$db->execute();
					}
					catch (RuntimeException $e)
					{
						JLog::add('Couldn\'t save fabrik comment notification for admin: ' + $db->stderr(true), JLog::WARNING, 'fabrik');
					}
				}
			}
		}
	}

	/**
	 * Test if the notification plugin is installed
	 *
	 * @return  unknown_type
	 */

	protected function notificationPluginInstalled()
	{
		return FabrikWorker::getPluginManager()->pluginExists('cron', 'notification');
	}

	/**
	 * Is the notification plugin installed and have we set the comment plugin option 'Use notification cron plugin'
	 * to something other than 'no'
	 *
	 * @return bool
	 */
	protected function useNotificationPlugin()
	{
		$params = $this->getparams();

		return $this->notificationPluginInstalled() && (int) $params->get('comment-plugin-notify') !== 0;
	}

	/**
	 * Thumb the comment
	 *
	 * @return boolean
	 */

	private function doThumbs()
	{
		$params = $this->getParams();

		return $params->get('comment-thumb') && FabrikWorker::getPluginManager()->pluginExists('element', 'thumbs');
	}

	/**
	 * Default send notifications code (sends to all people who have commented PLUS all admins)
	 *
	 * @param   object  $row       Notification
	 * @param   array   $comments  Objects
	 *
	 * @return  void
	 */

	protected function sentNotifications($row, $comments)
	{
		$formModel = $this->getModel();
		$app = JFactory::getApplication();
		$params = $this->getParams();
		$sentto = array();
		$title = FText::_('PLG_FORM_COMMENT_NEW_COMMENT_ADDED_TITLE');
		$message = FText::_('PLG_FORM_COMMENT_NEW_COMMENT_ADDED');
		$message .= "<br /><a href=\"{$row->url}\">" . FText::_('PLG_FORM_COMMENT_VIEW_COMMENT') . "</a>";
		$mail = JFactory::getMailer();

		if ((int) $params->get('comment-internal-notify') == 1)
		{
			$onlySubscribed = (bool) $params->get('comment_allow_user_subscriptions_to_notifications');

			foreach ($comments as $comment)
			{
				if ($comment->id == $row->id)
				{
					// Don't sent notification to user who just posted
					continue;
				}

				$shouldSend = $onlySubscribed === false || ($onlySubscribed && (int) $comment->notify === 1);

				if ($shouldSend && !in_array($comment->email, $sentto))
				{
					$mail->sendMail($app->getCfg('mailfrom'), $app->getCfg('fromname'), $comment->email, $title, $message, true);
					$sentto[] = $comment->email;
				}
			}
		}

		// Notify original poster (hack for ideenbus)
		$listModel = $formModel->getlistModel();
		$rowdata = $listModel->getRow($row->row_id);

		if (isset($rowdata->ide_idea___email_raw) && !in_array($rowdata->ide_idea___email_raw, $sentto))
		{
			$mail->sendMail($app->getCfg('mailfrom'), $app->getCfg('fromname'), $rowdata->ide_idea___email_raw, $title, $message, true);
			$sentto[] = $rowdata->ide_idea___email_raw;
		}

		if ($params->get('comment-notify-admins') == 1)
		{
			// Notify admins
			// Get all super administrator
			$rows = $this->getAdminInfo();

			foreach ($rows as $row)
			{
				if (!in_array($row->email, $sentto))
				{
					$mail->sendMail($app->getCfg('mailfrom'), $app->getCfg('fromname'), $row->email, $title, $message, true);
					$sentto[] = $row->email;
				}
			}
		}
	}

	/**
	 * Get email
	 *
	 * @return  void
	 */

	public function onGetEmail()
	{
		$app = JFactory::getApplication();
		$commentid = $app->input->getInt('commentid');

		// TableComment
		$c = FabTable::getInstance('Comment', 'FabrikTable');
		$c->load($commentid);
		echo "<a href=\"mailto:$c->email\">$c->email</a>";
	}

	/**
	 * Prepare intense debate comment system
	 *
	 * @return  void
	 */

	protected function _intensedebate()
	{
		$params = $this->getParams();
		FabrikHelperHTML::addScriptDeclaration(
			"
				var idcomments_acct = '" . $params->get('comment-intesedebate-code') . "';
						var idcomments_post_id;
						var idcomments_post_url;");
		$this->data = '
				<span id="IDCommentsPostTitle" style="display:none"></span>
				<script type=\'text/javascript\' src=\'http://www.intensedebate.com/js/genericCommentWrapperV2.js\'></script>';
	}

	/**
	 * Prepare disqus comment system
	 *
	 * @return  void
	 */

	protected function _disqus()
	{
		$params = $this->getParams();
		$app = JFactory::getApplication();
		$input = $app->input;

		if ($input->get('ajax') == 1)
		{
			$this->data = '';

			return;
		}

		FabrikHelperHTML::addScriptDeclaration(
			"
				(function() {
				var links = document.getElementsByTagName('a');
				var query = '?';
				for (var i = 0; i < links.length; i++) {
				if(links[i].href.indexOf('#disqus_thread') >= 0) {
				query += 'url' + i + '=' + encodeURIComponent(links[i].href) + '&';
	}
	}
				document.write('<script type=\"text/javascript\" src=\"http://disqus.com/forums/rotterdamvooruit/get_num_replies.js' + query + '\">
			</' + 'script>');
	})();
				");
		$this->data = '<div id="disqus_thread"></div><script type="text/javascript" src="http://disqus.com/forums/'
			. $params->get('comment-disqus-subdomain') . '/embed.js"></script><noscript>'
			. '<a href="http://rotterdamvooruit.disqus.com/?url=ref">View the discussion thread.</a>'
			. '</noscript><a href="http://disqus.com" class="dsq-brlink">blog comments powered by <span class="logo-disqus">Disqus</span></a>';
	}

	/**
	 * Prepare JComment system
	 *
	 * @return  void
	 */

	protected function _jcomment()
	{
		$formModel = $this->getModel();
		$app = JFactory::getApplication();
		$input = $app->input;
		$jcomments = JPATH_SITE . '/components/com_jcomments/jcomments.php';

		if (JFile::exists($jcomments))
		{
			require_once $jcomments;

			if ($this->commentsLocked)
			{
				$jc_config = JCommentsFactory::getConfig();
				$jc_config->set('comments_locked', 1);
			}

			$this->data = '<div id="jcomments" style="clear: both;">
					' . JComments::show($input->get('rowid', '', 'string'), "com_fabrik_{$formModel->getId()}") . '
							</div>';
		}
		else
		{
			throw new RuntimeException('JComment is not installed on your system');
		}
	}
}
