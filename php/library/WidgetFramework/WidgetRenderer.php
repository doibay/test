<?php
abstract class WidgetFramework_WidgetRenderer {
	/**
	 * Required method: define basic configuration of the renderer.
	 * Available configuration parameters:
	 * 	- name: The display name of the renderer
	 * 	- options: An array of renderer's options
	 * 	- useCache: Flag to determine the renderer can be cached or not
	 * 	- useUserCache: Flag to determine the renderer needs to be cached by an user-basis.
	 * 					Internally, this is implemented by getting the current user permission
	 * 					combination id (not the user id as normally expected). This is done to
	 * 					make sure the cache is used effectively
	 * 	- useLiveCache: Flag to determine the renderer wants to by pass writing to database 
	 * 					when it's being cached. This may be crucial if the renderer does a lot
	 * 					of thing on a big board. It's recommended to use a option for this
	 * 					because not all forum owner has a live cache system setup (XCache/memcached)
	 * 	- cacheSeconds: A numeric value to specify the maximum age of the cache (in seconds). 
	 * 					If the cache is too old, the widget will be rendered from scratch
	 * 	- useWrapper: Flag to determine the widget should be wrapped with a wrapper. Renderers
	 * 					that support wrapper will have an additional benefits of tabs: only
	 * 					wrapper-enabled widgets will be possible to use in tabbed interface
	 */
	abstract protected function _getConfiguration();
	
	/**
	 * Required method: get the template title of the options template (to be used in AdminCP).
	 * If this is not used, simply returns false.
	 */
	abstract protected function _getOptionsTemplate();
	
	/**
	 * Required method: get the template title of the render template (to be used in front-end).
	 * 
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 */
	abstract protected function _getRenderTemplate(array $widget, $positionCode, array $params);
	
	/**
	 * Required method: prepare data or whatever to get the render template ready to be rendered.
	 * 
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 * @param XenForo_Template_Abstract $renderTemplateObject
	 */
	abstract protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject);
	
	protected function _renderOptions(XenForo_Template_Abstract $template) { return true; }
	protected function _validateOptionValue($optionKey, &$optionValue) { return true; }
	protected function _getRequiredExternal(array $widget) {
		return array(
			/* example:
			 * 
			 * array('js', 'js/xenforo/cool-effect.js'), 
			 * array('css', 'beautiful-style-with-no-extension'),
			 * 
			 * */
		);
	}
	protected function _prepare(array $widget, $positionCode, array $params) { return true; }
	protected function _getExtraDataLink(array $widget) { return false; }
	
	const FORUMS_OPTION_SPECIAL_CURRENT = 'current_forum';
	const FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN = 'current_forum_and_children';
	const FORUMS_OPTION_SPECIAL_PARENT = 'parent_forum';
	const FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN = 'parent_forum_and_children';
	
	/**
	 * Helper method to prepare source array for <xen:select /> or similar tags
	 * 
	 * @param array $selected an array of selected values
	 * @param bool $useSpecialForums flag to determine the usage of special forum indicator
	 */
	protected function _helperPrepareForumsOptionSource(array $selected = array(), $useSpecialForums = false) {
		$forums = array();
		$nodes = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node')->getAllNodes();
		
		if ($useSpecialForums) {
			foreach (array(
					self::FORUMS_OPTION_SPECIAL_CURRENT,
					self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN,
					self::FORUMS_OPTION_SPECIAL_PARENT,
					self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN,
				) as $specialId) {
				$forums[] = array(
					'value' => $specialId,
					'label' => new XenForo_Phrase('wf_' . $specialId),
					'selected' => in_array($specialId, $selected),
				);
			}
		}
		
		foreach ($nodes as $node) {
			if ($node['node_type_id'] != 'Forum') continue;
			
			$forums[] = array(
				'value' => $node['node_id'],
				'label' => str_repeat('--', $node['depth']) . ' ' . $node['title'],
				'selected' => in_array($node['node_id'], $selected),
			);
		}
		
		return $forums;
	}
	
	/**
	 * Helper method to look for special forum ids in an array of forum ids
	 * 
	 * @param array $forumIds
	 */
	protected function _helperDetectSpecialForums($forumIds) {
		if (!is_array($forumIds)) {
			return false;
		}
		
		foreach ($forumIds as $forumId) {
			switch ($forumId) {
			case self::FORUMS_OPTION_SPECIAL_CURRENT:
			case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
			case self::FORUMS_OPTION_SPECIAL_PARENT:
			case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Helper method to get an array of forum ids ready to be used.
	 * The forum ids are taken after processing the `forums` option.
	 * Look into the source code of built-in renderer to understand 
	 * how to use this method.
	 * 
	 * @param array $forumsOption the `forums` option
	 * @param array $templateParams depending on the option, this method
	 * 				requires information from the template params.
	 * @param bool $asGuest flag to use guest permissions instead of 
	 * 				current user permissions
	 * 
	 * @return array of forum ids
	 */
	protected function _helperGetForumIdsFromOption(array $forumsOption, array $templateParams = array(), $asGuest = false) {
		if (empty($forumsOption)) {
			$forumIds = array_keys($this->_helperGetViewableNodeList($asGuest));
		} else {
			$forumIds = array_values($forumsOption);
			$forumIds2 = array();
			
			foreach (array_keys($forumIds) as $i) {
				switch ($forumIds[$i]) {
					case self::FORUMS_OPTION_SPECIAL_CURRENT:
						if (isset($templateParams['forum'])) {
							$forumIds2[] = $templateParams['forum']['node_id'];
						}
						unset($forumIds[$i]); // remove because it's not a valid forum id anyway
						break;
					case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
						if (isset($templateParams['forum'])) {
							$viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
							$forumIds2[] = $templateParams['forum']['node_id'];
							$this->_helperMergeChildForumIds($forumIds2, $viewableNodeList, $templateParams['forum']['node_id']);
						}
						unset($forumIds[$i]); // remove because it's not a valid forum id anyway
						break;
					case self::FORUMS_OPTION_SPECIAL_PARENT:
						if (isset($templateParams['forum'])) {
							$forumIds2[] = $templateParams['forum']['parent_node_id'];
						}
						unset($forumIds[$i]); // remove because it's not a valid forum id anyway
						break;
					case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
						if (isset($templateParams['forum'])) {
							$viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
							$forumIds2[] = $templateParams['forum']['parent_node_id'];
							$this->_helperMergeChildForumIds($forumIds2, $viewableNodeList, $templateParams['forum']['parent_node_id']);
						}
						unset($forumIds[$i]); // remove because it's not a valid forum id anyway
						break;
				}
			}
			
			if (!empty($forumIds2)) {
				// only merge 2 arrays if some new ids are found...
				$forumIds = array_unique(array_merge($forumIds, $forumIds2));
			}
		}
		
		return $forumIds;
	}
	
	/**
	 * Helper method to traverse a list of nodes looking for 
	 * children forums of a specified node
	 * 
	 * @param unknown_type $forumIds the result array (this array will be modified)
	 * @param unknown_type $nodes the nodes array to process
	 * @param unknown_type $parentNodeId the parent node id to use and check against
	 */
	protected function _helperMergeChildForumIds(array &$forumIds, array &$nodes, $parentNodeId) {
		foreach ($nodes as $node) {
			if ($node['parent_node_id'] == $parentNodeId) {
				$forumIds[] = $node['node_id'];
				$this->_helperMergeChildForumIds($forumIds, $nodes, $node['node_id']);
			}
		}
	}
	
	/**
	 * Helper method to get viewable node list. Renderers need this information
	 * should use call this method to get it. The node list is queried and cached
	 * to improve performance.
	 * 
	 * @param $asGuest flag to use guest permissions instead of current user permissions
	 * 
	 * @return array of viewable node (node_id as array key)
	 */
	protected function _helperGetViewableNodeList($asGuest) {
		if ($asGuest) {
			return $this->_helperGetViewableNodeListGuestOnly();
		}
		
		static $viewableNodeList = false;
		
		if ($viewableNodeList === false) {
			$viewableNodeList = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(); 
		}
		
		return $viewableNodeList;
	}
	
	protected function _helperGetViewableNodeListGuestOnly() {
		static $viewableNodeList = false;
		
		if ($viewableNodeList === false) {
			/* @var $nodeModel XenForo_Model_Node */
			$nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');
			
			$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(1);
			$viewableNodeList = $nodeModel->getViewableNodeList($nodePermissions); 
		}
		
		return $viewableNodeList;
	}
	
	protected static $_widgetTemplates = array();
	protected $_configuration = false;
	
	public function getConfiguration() {
		if ($this->_configuration === false) {
			$default = array(
				'name' => 'Name',
				'options' => array(),
				'useCache' => false, // output of this widget can be cached
				'useUserCache' => false,  // output should be cached by user permission (must have `useCache` enabled)
				'useLiveCache' => false, // output will be cached with live cache only (bypass database completely)
				'cacheSeconds' => 0, // cache older will be ignored, 0 means forever
				'useWrapper' => true,
			);
			
			$this->_configuration = XenForo_Application::mapMerge($default, $this->_getConfiguration());
			
			if ($this->_configuration['useWrapper']) {
				$this->_configuration['options']['tab_group'] = XenForo_Input::STRING;
			}
			
			$this->_configuration['options']['expression'] = XenForo_Input::STRING;
			$this->_configuration['options']['expression_debug'] = XenForo_Input::UINT;
		}
		
		return $this->_configuration;
	}
	
	public function getName() {
		$configuration = $this->getConfiguration();
		return $configuration['name'];
	}
	
	public function useWrapper(array $widget) {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useWrapper']);
	}
	
	public function useCache(array $widget) {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useCache']);
	}
	
	public function useUserCache(array $widget) {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useUserCache']);
	}
	
	public function useLiveCache(array $widget) {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useLiveCache']);
	}
	
	public function renderOptions(XenForo_ViewRenderer_Abstract $viewRenderer, array &$templateParams) {
		$templateParams['namePrefix'] = self::getNamePrefix();
		$templateParams['options_loaded'] = get_class($this);
		$templateParams['options'] = (!empty($templateParams['widget']['options']))?$templateParams['widget']['options']:array();
		$templateParams['rendererConfiguration'] = $this->getConfiguration();
		
		if ($this->_getOptionsTemplate()) {
			$optionsTemplate = $viewRenderer->createTemplateObject($this->_getOptionsTemplate(), $templateParams);
			
			$this->_renderOptions($optionsTemplate);
			
			$templateParams['optionsRendered'] = $optionsTemplate->render();
		}
	}
	
	public function parseOptionsInput(XenForo_Input $input, array $widget) {
		$configuration = $this->getConfiguration();
		$options = empty($widget['options'])?array():$widget['options'];
		
		foreach ($configuration['options'] as $optionKey => $optionType) {
			$optionValue = $input->filterSingle(self::getNamePrefix() . $optionKey, $optionType);
			if ($this->_validateOptionValue($optionKey, $optionValue) !== false) {
				$options[$optionKey] = $optionValue;
			}
		}

		return $options;
	}
	
	public function prepare(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template) {
		if ($this->useWrapper($widget)) {
			$template->preloadTemplate('wf_widget_wrapper');
			self::$_widgetTemplates['wf_widget_wrapper'] = true;
		}
		
		$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
		if (!empty($renderTemplate)) {
			$template->preloadTemplate($renderTemplate);
			self::$_widgetTemplates[$renderTemplate] = true;
		}
		
		$requiredExternal = $this->_getRequiredExternal($widget);
		if (!empty($requiredExternal)) {
			foreach ($requiredExternal as $requirement) {
				$template->addRequiredExternal($requirement[0], $requirement[1]);
			}	
		}
		
		$this->_prepare($widget, $positionCode, $params);
	}
	
	protected function _executeExpression($expression, array $params) {
		$expression = trim($expression);
		if (empty($expression)) return true;
		
		$sandbox = @create_function('$params', 'extract($params); return (' . $expression . ');');
		
		if (!empty($sandbox)) {
			return call_user_func($sandbox, $params);
		} else {
			throw new Exception('Syntax error');
		}				
	}
	
	protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array()) {
		if (empty($suffix)) {
			return $widget['widget_id'];
		} else {
			return $widget['widget_id'] . '__' . implode('_', $suffix);
		}
	}
	
	public function render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template, &$output) {
		$html = false;

		// always check for expression if it's available
		// otherwise the cached widget will show up every where... (the cache test also moved down below this)
		// since 1.2.1
		if (isset($widget['options']['expression'])) {
			try {
				if (!$this->_executeExpression($widget['options']['expression'], $params)) {
					// exepression failed, stop rendering...
					$html = '';
				}
			} catch (Exception $e) {
				// problem executing expression... Stop rendering anyway
				if (!empty($widget['options']['expression_debug'])) {
					$html = $e->getMessage();
				} else {
					$html = '';
				}
			}
		}
		
		// check for cache after expression test
		// since 1.2.1
		$cacheId = false;
		$useUserCache = false;
		$useLiveCache = false;
		if ($html === false AND $this->useCache($widget)) {
			// get the cache id
			// previously, the cache id is the same as the widget id
			// but now it can be something else if the renderer wants to do that
			// since 1.3
			$cacheId = $this->_getCacheId($widget, $positionCode, $params);
			$useUserCache = $this->useUserCache($widget);
			$useLiveCache = $this->useLiveCache($widget);
			
			$cached = WidgetFramework_Core::loadCachedWidget($cacheId, $useUserCache, $useLiveCache);
			if (!empty($cached) AND is_array($cached) AND $this->isCacheUsable($cached, $widget)) {
				$html = $cached['html'];
			}
		}
		
		// expression executed just fine
		if ($html === false) {
			$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
			if (!empty($renderTemplate)) {
				$renderTemplateObject = $template->create($renderTemplate, $params);
				$renderTemplateObject->setParam('widget', $widget);
				$html = $this->_render($widget, $positionCode, $params, $renderTemplateObject);
			} else {
				$html = $this->_render($widget, $positionCode, $params, $template);
			}
			$html = trim($html);
			
			if ($cacheId !== false) {
				WidgetFramework_Core::saveCachedWidget($cacheId, $html, $useUserCache, $useLiveCache);
			}
		}
		
		return trim($html);
	}
	
	public function extraPrepare(array $widget, &$html) {
		return array(
			'link' => $this->_getExtraDataLink($widget),
			// want extra data here?
			// simply override this method in sub-classes
		);
	}
	
	public function isCacheUsable(array &$cached, array $widget) {
		$configuration = $this->getConfiguration();
		if (empty($configuration['useCache'])) return false; // what?
		if ($configuration['cacheSeconds'] <= 0) return true;

		$seconds = XenForo_Application::$time - $cached['time'];
		if ($seconds > $configuration['cacheSeconds']) return false;

		return true;
	}
	
	public static function wrap(array $tabs, XenForo_Template_Abstract $template, $groupId = false) {
		if ($groupId === false) $groupId = 'widget-rand-' . rand(1000,9999);
		$groupId = preg_replace('/[^a-zA-Z0-9\-]/', '', $groupId);
		
		$wrapper = $template->create('wf_widget_wrapper', array('tabs' => $tabs, 'groupId' => $groupId));
		
		return $wrapper->render();
	}
	
	public static function create($class) {
		// TODO: do we need to resolve dynamic class?
		/*
		$createClass = XenForo_Application::resolveDynamicClass($class, 'widget_renderer');
		if (!$createClass) {
			throw new XenForo_Exception("Invalid widget renderer '$class' specified");
		}
		*/
		$createClass = $class;

		if (class_exists($createClass)) {
			return new $createClass;
		} else {
			throw new XenForo_Exception("Invalid widget renderer '$class' specified");
		}
	}
	
	public static function getNamePrefix() {
		return 'options_';
	}
	
	public static function isIgnoredTemplate($templateName) {
		if (!empty(self::$_widgetTemplates[$templateName])) {
			return true;
		} elseif (strtolower(substr($templateName, -4)) == '.css') {
			// sondh@2012-08-20
			// do not prepare for CSS templates
			return true;
		}
		
		return false;
	}
}