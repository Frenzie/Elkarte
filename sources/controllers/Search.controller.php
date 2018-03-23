<?php

/**
 * Handle all of the searching from here.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Search_Controller class
 * Handle all of the searching for the site
 *
 * @package Search
 */
class Search_Controller extends Action_Controller
{
	/**
	 * Holds the search object
	 * @var \ElkArte\Search\Search
	 */
	protected $_search = null;

	/**
	 * The class that takes care of rendering the message icons (MessageTopicIcons)
	 * @var null|MessageTopicIcons
	 */
	protected $_icon_sources = null;

	/**
	 * Called before any other action method in this class.
	 *
	 * - If coming from the quick reply allows to route to the proper action
	 * - if needed (for example external search engine or members search
	 */
	public function pre_dispatch()
	{
		global $modSettings, $scripturl;

		// Coming from quick search box and going to some custom place?
		if (isset($_REQUEST['search_selection']) && !empty($modSettings['additional_search_engines']))
		{
			$engines = prepareSearchEngines();
			if (isset($engines[$_REQUEST['search_selection']]))
			{
				$engine = $engines[$_REQUEST['search_selection']];
				redirectexit($engine['url'] . urlencode(implode($engine['separator'], explode(' ', $_REQUEST['search']))));
			}
		}

		// If coming from the quick search box, and we want to search on members, well we need to do that ;)
		if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members')
		{
			redirectexit($scripturl . '?action=memberlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));
		}

		// If load management is on and the load is high, no need to even show the form.
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new Elk_Exception('loadavg_search_disabled', false);
		}
	}

	/**
	 * Intended entry point for this class.
	 *
	 * - The default action for no sub-action is... present the search screen
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method.
		$this->action_search();
	}

	/**
	 * Ask the user what they want to search for.
	 *
	 * What it does:
	 *
	 * - Shows the screen to search forum posts (action=search),
	 * - Uses the main sub template of the Search template.
	 * - Uses the Search language file.
	 * - Requires the search_posts permission.
	 * - Decodes and loads search parameters given in the URL (if any).
	 * - The form redirects to index.php?action=search;sa=results.
	 *
	 * @uses Search language file and Errors language when needed
	 * @uses Search template, searchform sub template
	 */
	public function action_search()
	{
		global $txt, $scripturl, $modSettings, $user_info, $context;

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
			throw new Elk_Exception('loadavg_search_disabled', false);

		theme()->getTemplates()->loadLanguageFile('Search');

		// Don't load this in XML mode.
		if (!isset($_REQUEST['xml']))
		{
			theme()->getTemplates()->load('Search');
			$context['sub_template'] = 'searchform';
			loadJavascriptFile('suggest.js', array('defer' => true));
		}

		// Check the user's permissions.
		isAllowedTo('search_posts');

		// Link tree....
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=search',
			'name' => $txt['search']
		);

		// This is hard coded maximum string length.
		$context['search_string_limit'] = 100;

		$context['require_verification'] = $user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
		if ($context['require_verification'])
		{
			// Build a verification control for the form
			$verificationOptions = array(
				'id' => 'search',
			);

			$context['require_verification'] = VerificationControls_Integrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		// If you got back from search;sa=results by using the linktree, you get your original search parameters back.
		if ($this->_search === null && isset($_REQUEST['params']))
		{
			Elk_Autoloader::instance()->register(SUBSDIR . '/Search', '\\ElkArte\\Search');
			$search_params = new \ElkArte\Search\SearchParams($_REQUEST['params'] ?? '');

			$context['search_params'] = $search_params->get();
		}

		if (isset($_REQUEST['search']))
			$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT, 'UTF-8');
		if (!empty($context['search_params']['searchtype']))
			$context['search_params']['searchtype'] = 2;
		if (!empty($context['search_params']['minage']))
			$context['search_params']['minage'] = (int) $context['search_params']['minage'];
		if (!empty($context['search_params']['maxage']))
			$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

		$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
		$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

		// Load the error text strings if there were errors in the search.
		if (!empty($context['search_errors']))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['search_errors']['messages'] = array();
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
					continue;

				if ($search_error === 'string_too_long')
					$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);

				$context['search_errors']['messages'][] = $txt['error_' . $search_error];
			}
		}

		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('not_redirection' => true));

		$context['boards_in_category'] = array();
		foreach ($context['categories'] as $cat => &$category)
		{
			$context['boards_in_category'][$cat] = count($category['boards']);
			$category['child_ids'] = array_keys($category['boards']);
			foreach ($category['boards'] as &$board)
				$board['selected'] = (empty($context['search_params']['brd']) && (empty($modSettings['recycle_enable']) || $board['id'] != $modSettings['recycle_board']) && !in_array($board['id'], $user_info['ignoreboards'])) || (!empty($context['search_params']['brd']) && in_array($board['id'], $context['search_params']['brd']));
		}

		if (!empty($_REQUEST['topic']))
		{
			$context['search_params']['topic'] = (int) $_REQUEST['topic'];
			$context['search_params']['show_complete'] = true;
		}

		if (!empty($context['search_params']['topic']))
		{
			$context['search_params']['topic'] = (int) $context['search_params']['topic'];

			$context['search_topic'] = array(
				'id' => $context['search_params']['topic'],
				'href' => $scripturl . '?topic=' . $context['search_params']['topic'] . '.0',
			);

			require_once(SUBSDIR . '/Topic.subs.php');
			$context['search_topic']['subject'] = getSubject($context['search_params']['topic']);
			$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
		}

		$context['page_title'] = $txt['set_parameters'];
		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
			$context['minmax_preferences']['asearch'] = 1;

		call_integration_hook('integrate_search');
	}

	/**
	 * Gather the results and show them.
	 *
	 * What it does:
	 *
	 * - Checks user input and searches the messages table for messages matching the query.
	 * - Requires the search_posts permission.
	 * - Uses the results sub template of the Search template.
	 * - Uses the Search language file.
	 * - Stores the results into the search cache.
	 * - Show the results of the search query.
	 */
	public function action_results()
	{
		global $scripturl, $modSettings, $txt, $settings;
		global $user_info, $context, $options, $messages_request, $boards_can;

		// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
		stop_prefetching();

		// These vars don't require an interface, they're just here for tweaking.
		$recentPercentage = 0.30;
		$humungousTopicPosts = 200;
		$maxMembersToSearch = 500;
		$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] * 5;

		// Start with no errors.
		$context['search_errors'] = array();

		// Number of pages hard maximum - normally not set at all.
		$modSettings['search_max_results'] = empty($modSettings['search_max_results']) ? 200 * $modSettings['search_results_per_page'] : (int) $modSettings['search_max_results'];

		// Maximum length of the string.
		$context['search_string_limit'] = 100;

		theme()->getTemplates()->loadLanguageFile('Search');
		if (!isset($_REQUEST['xml']))
			theme()->getTemplates()->load('Search');
		// If we're doing XML we need to use the results template regardless really.
		else
			$context['sub_template'] = 'results';

		// Are you allowed?
		isAllowedTo('search_posts');

		$this->_search = new \ElkArte\Search\Search($humungousTopicPosts, $maxMessageResults);
		$this->_search->setWeights(new \ElkArte\Search\WeightFactors($modSettings, $user_info['is_admin']));
		$search_params = new \ElkArte\Search\SearchParams($_REQUEST['params'] ?? '');
		$search_params->merge($_REQUEST, $recentPercentage, $maxMembersToSearch);
		$this->_search->setParams($search_params, !empty($modSettings['search_simple_fulltext']));

		$context['compact'] = $this->_search->isCompact();

		// Nothing??
		if ($this->_search->param('search') === false || $this->_search->param('search') === '')
			$context['search_errors']['invalid_search_string'] = true;
		// Too long?
		elseif (Util::strlen($this->_search->param('search')) > $context['search_string_limit'])
			$context['search_errors']['string_too_long'] = true;

		// Build the search array
		// $modSettings ['search_simple_fulltext'] is an hidden setting that will
		// do fulltext searching in the most basic way.
		$searchArray = $this->_search->getSearchArray();

		// This is used to remember words that will be ignored (because too short usually)
		$context['search_ignored'] = $this->_search->getIgnored();

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
		{
			if (!empty($context['search_ignored']))
				$context['search_errors']['search_string_small_words'] = true;
			else
				$context['search_errors']['invalid_search_string' . ($this->_search->foundBlackListedWords() ? '_blacklist' : '')] = true;

			// Don't allow duplicate error messages if one string is too short.
			if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
				unset($context['search_errors']['invalid_search_string']);
		}

		// *** Spell checking?
		if (!empty($modSettings['enableSpellChecking']) && function_exists('pspell_new'))
		{
			$context['did_you_mean'] = '';
			$context['did_you_mean_params'] = '';
			// @todo maybe move the html to a $settings
			$this->_search->loadSuggestions($context['did_you_mean'], $context['did_you_mean_params'], '<em><strong>{word}</strong></em>');
		}

		// Let the user adjust the search query, should they wish?
		$context['search_params'] = $this->_search->getParams();
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);
		if (empty($context['search_params']['minage']))
			$context['search_params']['minage'] = 0;
		if (empty($context['search_params']['maxage']))
			$context['search_params']['maxage'] = 9999;

		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		$this->_controlVerifications();

		$context['params'] = $this->_search->compileURLparams();

		// ... and add the links to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=search;params=' . $context['params'],
			'name' => $txt['search']
		);

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=search;sa=results;params=' . $context['params'],
			'name' => $txt['search_results']
		);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
			$context['minmax_preferences']['asearch'] = 1;

		// *** A last error check
		call_integration_hook('integrate_search_errors');

		// One or more search errors? Go back to the first search screen.
		if (!empty($context['search_errors']))
			return $this->action_search();

		// Spam me not, Spam-a-lot?
		if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $this->_search->param('search'))
			spamProtection('search');

		// Store the last search string to allow pages of results to be browsed.
		$_SESSION['last_ss'] = $this->_search->param('search');

		try
		{
			$context['topics'] = $this->_search->searchQuery(
				new \ElkArte\Search\SearchApi(!empty($modSettings['search_index']) ? $modSettings['search_index'] : '')
			);
		}
		catch (\Exception $e)
		{
			$context['search_errors'][$e->getMessage()] = true;
			return $this->action_search();
		}

		if (!empty($context['topics']))
		{
			// Create an array for the permissions.
			$boards_can = boardsAllowedTo(array('post_reply_own', 'post_reply_any', 'mark_any_notify'), true, false);

			// How's about some quick moderation?
			if (!empty($options['display_quick_mod']))
			{
				$boards_can = array_merge($boards_can, boardsAllowedTo(array('lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'), true, false));

				$context['can_lock'] = in_array(0, $boards_can['lock_any']);
				$context['can_sticky'] = in_array(0, $boards_can['make_sticky']);
				$context['can_move'] = in_array(0, $boards_can['move_any']);
				$context['can_remove'] = in_array(0, $boards_can['remove_any']);
				$context['can_merge'] = in_array(0, $boards_can['merge_any']);
			}

			// What messages are we using?
			$msg_list = array_keys($context['topics']);
			$posters = $this->_search->loadPosters($msg_list, count($context['topics']));

			call_integration_hook('integrate_search_message_list', array(&$msg_list, &$posters));

			if (!empty($posters))
				loadMemberData(array_unique($posters));

			// Get the messages out for the callback - select enough that it can be made to look just like Display.
			$messages_request = $this->_search->loadMessagesRequest($msg_list, count($context['topics']));

			// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
			if ($this->_search->noMessages($messages_request))
				$context['topics'] = array();

			$this->_prepareParticipants(!empty($modSettings['enableParticipation']), $user_info['is_guest'] ? $user_info['id'] : 0);
		}

		// Now that we know how many results to expect we can start calculating the page numbers.
		$context['page_index'] = constructPageIndex($scripturl . '?action=search;sa=results;params=' . $context['params'], $_REQUEST['start'], $this->_search->getNumResults(), $modSettings['search_results_per_page'], false);

		// Consider the search complete!
		Cache::instance()->remove('search_start:' . ($user_info['is_guest'] ? $user_info['ip'] : $user_info['id']));

		$context['sub_template'] = 'results';
		$context['page_title'] = $txt['search_results'];
		$context['topic_starter_id'] = 0;
		$context['get_topics'] = array($this, 'prepareSearchContext_callback');
		$this->_icon_sources = new MessageTopicIcons(!empty($modSettings['messageIconChecks_enable']), $settings['theme_dir']);

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => addslashes(un_htmlspecialchars($txt['select_destination'])),
		);
	}

	protected function _controlVerifications()
	{
		global $user_info, $modSettings, $context;

		// Do we have captcha enabled?
		if ($user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $this->_search->param('search')))
		{
			// If we come from another search box tone down the error...
			if (!isset($_REQUEST['search_vv']))
			{
				$context['search_errors']['need_verification_code'] = true;
			}
			else
			{
				$verificationOptions = array(
					'id' => 'search',
				);
				$context['require_verification'] = VerificationControls_Integrate::create($verificationOptions, true);

				if (is_array($context['require_verification']))
				{
					foreach ($context['require_verification'] as $error)
						$context['search_errors'][$error] = true;
				}
				// Don't keep asking for it - they've proven themselves worthy.
				else
					$_SESSION['ss_vv_passed'] = true;
			}
		}
	}

	protected function _prepareParticipants($participationEnabled, $user_id)
	{
		// If we want to know who participated in what then load this now.
		if ($participationEnabled === true && $user_id !== 0)
		{
			$this->_participants = $this->_search->getParticipants();

			require_once(SUBSDIR . '/MessageIndex.subs.php');
			$topics_participated_in = topicsParticipation($user_id, array_keys($this->_participants));

			foreach ($topics_participated_in as $topic)
				$this->_participants[$topic['id_topic']] = true;
		}
	}

	/**
	 * Callback to return messages - saves memory.
	 *
	 * @todo Fix this, update it, whatever... from Display.controller.php mainly.
	 * Note that the call to loadAttachmentContext() doesn't work:
	 * this function doesn't fulfill the pre-condition to fill $attachments global...
	 * So all it does is to fallback and return.
	 *
	 * What it does:
	 *
	 * - Callback function for the results sub template.
	 * - Loads the necessary contextual data to show a search result.
	 *
	 * @param boolean $reset = false
	 * @return array of messages that match the search
	 */
	public function prepareSearchContext_callback($reset = false)
	{
		global $txt, $modSettings, $scripturl, $user_info;
		global $memberContext, $context, $options, $messages_request;
		global $boards_can;

		// Remember which message this is.  (ie. reply #83)
		static $counter = null;
		if ($counter === null || $reset)
			$counter = $_REQUEST['start'] + 1;

		// Start from the beginning...
		if ($reset)
			return currentContext($messages_request, $reset);

		// Attempt to get the next in line
		$message = currentContext($messages_request);
		if (!$message)
			return false;

		// Can't have an empty subject can we?
		$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

		$message['first_subject'] = $message['first_subject'] != '' ? $message['first_subject'] : $txt['no_subject'];
		$message['last_subject'] = $message['last_subject'] != '' ? $message['last_subject'] : $txt['no_subject'];

		// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
		if (!loadMemberContext($message['id_member']))
		{
			// Notice this information isn't used anywhere else.... *cough guest table cough*.
			$memberContext[$message['id_member']]['name'] = $message['poster_name'];
			$memberContext[$message['id_member']]['id'] = 0;
			$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
			$memberContext[$message['id_member']]['link'] = $message['poster_name'];
			$memberContext[$message['id_member']]['email'] = $message['poster_email'];
		}
		$memberContext[$message['id_member']]['ip'] = $message['poster_ip'];

		// Do the censor thang...
		$message['body'] = censor($message['body']);
		$message['subject'] = censor($message['subject']);
		$message['first_subject'] = censor($message['first_subject']);
		$message['last_subject'] = censor($message['last_subject']);

		$searchArray = $this->_search->getSearchArray();

		// Shorten this message if necessary.
		if ($context['compact'])
		{
			// Set the number of characters before and after the searched keyword.
			$charLimit = 50;

			$message['body'] = strtr($message['body'], array("\n" => ' ', '<br />' => "\n"));
			$bbc_parser = \BBC\ParserWrapper::instance();
			$message['body'] = $bbc_parser->parseMessage($message['body'], $message['smileys_enabled']);
			$message['body'] = strip_tags(strtr($message['body'], array('</div>' => '<br />', '</li>' => '<br />')), '<br>');

			if (Util::strlen($message['body']) > $charLimit)
			{
				if (empty($searchArray))
				{
					$message['body'] = Util::substr($message['body'], 0, $charLimit) . '<strong>...</strong>';
				}
				else
				{
					$matchString = '';
					$force_partial_word = false;
					foreach ($searchArray as $keyword)
					{
						$keyword = un_htmlspecialchars($keyword);
						$keyword = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', strtr($keyword, array('\\\'' => '\'', '&' => '&amp;')));
						if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) != 0 || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) != 0)
							$force_partial_word = true;
						$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
					}
					$matchString = un_htmlspecialchars(substr($matchString, 0, -1));

					$message['body'] = un_htmlspecialchars(strtr($message['body'], array('&nbsp;' => ' ', '<br />' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

					if (empty($modSettings['search_method']) || $force_partial_word)
						preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/isu', $message['body'], $matches);
					else
						preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/isu', $message['body'], $matches);

					$message['body'] = '';
					foreach ($matches[0] as $match)
					{
						$match = strtr(htmlspecialchars($match, ENT_QUOTES, 'UTF-8'), array("\n" => '&nbsp;'));
						$message['body'] .= '<strong>&hellip;&hellip;</strong>&nbsp;' . $match . '&nbsp;<strong>&hellip;&hellip;</strong>';
					}
				}

				// Re-fix the international characters.
				$message['body'] = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $message['body']);
			}
		}
		else
		{
			// Run BBC interpreter on the message.
			$bbc_parser = \BBC\ParserWrapper::instance();
			$message['body'] = $bbc_parser->parseMessage($message['body'], $message['smileys_enabled']);
		}

		// Make sure we don't end up with a practically empty message body.
		$message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $message['body']);

		// Do we have quote tag enabled?
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		$output_pre = Topic_Util::prepareContext(array($message))[$message['id_topic']];

		$output = array_merge($context['topics'][$message['id_msg']], $output_pre);

		$output['posted_in'] = !empty($this->_participants[$message['id_topic']]);
		$output['tests'] = array(
			'can_reply' => in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any']),
			'can_quote' => (in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any'])) && $quote_enabled,
			'can_mark_notify' => in_array($message['id_board'], $boards_can['mark_any_notify']) || in_array(0, $boards_can['mark_any_notify']) && !$context['user']['is_guest'],
		);
		$output['board'] = array(
			'id' => $message['id_board'],
			'name' => $message['board_name'],
			'href' => $scripturl . '?board=' . $message['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $message['id_board'] . '.0">' . $message['board_name'] . '</a>'
		);
		$output['category'] = array(
			'id' => $message['id_cat'],
			'name' => $message['cat_name'],
			'href' => $scripturl . $modSettings['default_forum_action'] . '#c' . $message['id_cat'],
			'link' => '<a href="' . $scripturl . $modSettings['default_forum_action'] . '#c' . $message['id_cat'] . '">' . $message['cat_name'] . '</a>'
		);

		determineTopicClass($output);

		if ($output['posted_in'])
			$output['class'] = 'my_' . $output['class'];

		$body_highlighted = $message['body'];
		$subject_highlighted = $message['subject'];

		if (!empty($options['display_quick_mod']))
		{
			$started = $output['first_post']['member']['id'] == $user_info['id'];

			$output['quick_mod'] = array(
				'lock' => in_array(0, $boards_can['lock_any']) || in_array($output['board']['id'], $boards_can['lock_any']) || ($started && (in_array(0, $boards_can['lock_own']) || in_array($output['board']['id'], $boards_can['lock_own']))),
				'sticky' => (in_array(0, $boards_can['make_sticky']) || in_array($output['board']['id'], $boards_can['make_sticky'])),
				'move' => in_array(0, $boards_can['move_any']) || in_array($output['board']['id'], $boards_can['move_any']) || ($started && (in_array(0, $boards_can['move_own']) || in_array($output['board']['id'], $boards_can['move_own']))),
				'remove' => in_array(0, $boards_can['remove_any']) || in_array($output['board']['id'], $boards_can['remove_any']) || ($started && (in_array(0, $boards_can['remove_own']) || in_array($output['board']['id'], $boards_can['remove_own']))),
			);

			$context['can_lock'] |= $output['quick_mod']['lock'];
			$context['can_sticky'] |= $output['quick_mod']['sticky'];
			$context['can_move'] |= $output['quick_mod']['move'];
			$context['can_remove'] |= $output['quick_mod']['remove'];
			$context['can_merge'] |= in_array($output['board']['id'], $boards_can['merge_any']);
			$context['can_markread'] = $context['user']['is_logged'];

			$context['qmod_actions'] = array('remove', 'lock', 'sticky', 'move', 'markread');
			call_integration_hook('integrate_quick_mod_actions_search');
		}

		foreach ($searchArray as $query)
		{
			// Fix the international characters in the keyword too.
			$query = un_htmlspecialchars($query);
			$query = trim($query, '\*+');
			$query = strtr(Util::htmlspecialchars($query), array('\\\'' => '\''));

			$body_highlighted = preg_replace_callback('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/iu', array($this, '_highlighted_callback'), $body_highlighted);
			$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $subject_highlighted);
		}

		require_once(SUBSDIR . '/Attachments.subs.php');
		$output['matches'][] = array(
			'id' => $message['id_msg'],
			'attachment' => loadAttachmentContext($message['id_msg']),
			'alternate' => $counter % 2,
			'member' => &$memberContext[$message['id_member']],
			'icon' => $message['icon'],
			'icon_url' => $this->_icon_sources->{$message['icon']},
			'subject' => $message['subject'],
			'subject_highlighted' => $subject_highlighted,
			'time' => standardTime($message['poster_time']),
			'html_time' => htmlTime($message['poster_time']),
			'timestamp' => forum_time(true, $message['poster_time']),
			'counter' => $counter,
			'modified' => array(
				'time' => standardTime($message['modified_time']),
				'html_time' => htmlTime($message['modified_time']),
				'timestamp' => forum_time(true, $message['modified_time']),
				'name' => $message['modified_name']
			),
			'body' => $message['body'],
			'body_highlighted' => $body_highlighted,
			'start' => 'msg' . $message['id_msg']
		);
		$counter++;

		if (!$context['compact'])
		{
			$output['buttons'] = array(
				// Can we request notification of topics?
				'notify' => array(
					'href' => $scripturl . '?action=notify;topic=' . $output['id'] . '.msg' . $message['id_msg'],
					'text' => $txt['notify'],
					'test' => 'can_mark_notify',
				),
				// If they *can* reply?
				'reply' => array(
					'href' => $scripturl . '?action=post;topic=' . $output['id'] . '.msg' . $message['id_msg'],
					'text' => $txt['reply'],
					'test' => 'can_reply',
				),
				// If they *can* quote?
				'quote' => array(
					'href' => $scripturl . '?action=post;topic=' . $output['id'] . '.msg' . $message['id_msg'] . ';quote=' . $message['id_msg'],
					'text' => $txt['quote'],
					'test' => 'can_quote',
				),
			);
		}

		call_integration_hook('integrate_search_message_context', array($counter, &$output));

		return $output;
	}

	/**
	 * Used to highlight body text with strings that match the search term
	 *
	 * Callback function used in $body_highlighted
	 *
	 * @param string[] $matches
	 *
	 * @return string
	 */
	private function _highlighted_callback($matches)
	{
		return isset($matches[2]) && $matches[2] == $matches[1] ? stripslashes($matches[1]) : '<span class="highlight">' . $matches[1] . '</span>';
	}

	/**
	 * Fills the empty spaces in an array with the default values for search params
	 *
	 * @param mixed[] $array
	 *
	 * @return mixed[]
	 */
	private function _fill_default_search_params($array)
	{
		if (empty($array['search']))
			$array['search'] = '';
		if (empty($array['userspec']))
			$array['userspec'] = '*';
		if (empty($array['searchtype']))
			$array['searchtype'] = 0;

		if (!isset($array['show_complete']))
			$array['show_complete'] = 0;
		else
			$array['show_complete'] = (int) $array['show_complete'];

		if (!isset($array['subject_only']))
			$array['subject_only'] = 0;
		else
			$array['subject_only'] = (int) $array['subject_only'];

		if (empty($array['minage']))
			$array['minage'] = 0;
		if (empty($array['maxage']))
			$array['maxage'] = 9999;
		if (empty($array['sort']))
			$array['sort'] = 'relevance';

		return $array;
	}
}
