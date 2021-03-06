<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPKPAuthorFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPKPAuthorFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of authors
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlPKPAuthorFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML author import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'authors';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'author';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlPKPAuthorFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of PKPAuthor objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		// Create the data object
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$author = $authorDao->newDataObject();
		$author->setData('publicationId', $submission->getCurrentPublication()->getId());
		if ($node->getAttribute('primary_contact')) $author->setPrimaryContact(true);
		if ($node->getAttribute('include_in_browse')) $author->setIncludeInBrowse(true);

		// Identify the user group by name
		$userGroupName = $node->getAttribute('user_group_ref');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroups = $userGroupDao->getByContextId($context->getId());
		while ($userGroup = $userGroups->next()) {
			if (in_array($userGroupName, $userGroup->getName(null))) {
				// Found a candidate; stash it.
				$author->setUserGroupId($userGroup->getId());
				break;
			}
		}
		if (!$author->getUserGroupId()) {
			$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownUserGroup', array('param' => $userGroupName)));
		}

		// Handle metadata in subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) switch($n->tagName) {
			case 'givenname':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $submission->getLocale();
				$author->setGivenName($n->textContent, $locale);
				break;
			case 'familyname':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $submission->getLocale();
				$author->setFamilyName($n->textContent, $locale);
				break;
			case 'affiliation':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $submission->getLocale();
				$author->setAffiliation($n->textContent, $locale);
				break;
			case 'country': $author->setCountry($n->textContent); break;
			case 'email': $author->setEmail($n->textContent); break;
			case 'url': $author->setUrl($n->textContent); break;
			case 'orcid': $author->setOrcid($n->textContent); break;
			case 'biography':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $submission->getLocale();
				$author->setBiography($n->textContent, $locale);
				break;
		}

		if (empty($author->getGivenName($submission->getLocale()))) {
			$allLocales = AppLocale::getAllLocales();
			$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.missingGivenName', array('authorName' => $author->getLocalizedGivenName(), 'localeName' => $allLocales[$submission->getLocale()])));
		}
		$authorDao->insertObject($author);
		return $author;
	}
}


