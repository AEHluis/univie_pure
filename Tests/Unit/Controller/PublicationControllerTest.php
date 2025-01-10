<?php
namespace UNIVIE\UniviePure\Tests\Unit\Controller;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Christian Klettner <christian.klettner@univie.ac.at>, univie
 *  			
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Test case for class UNIVIE\UniviePure\Controller\PublicationController.
 *
 * @author Christian Klettner <christian.klettner@univie.ac.at>
 */
class PublicationControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \UNIVIE\UniviePure\Controller\PublicationController
	 */
	protected $subject = NULL;

	public function setUp() {
		$this->subject = $this->getMock('UNIVIE\\UniviePure\\Controller\\PublicationController', array('redirect', 'forward', 'addFlashMessage'), array(), '', FALSE);
	}

	public function tearDown() {
		unset($this->subject);
	}

	/**
	 * @test
	 */
	public function listActionFetchesAllPublicationsFromRepositoryAndAssignsThemToView() {

		$allPublications = $this->getMock('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage', array(), array(), '', FALSE);

		$publicationRepository = $this->getMock('', array('findAll'), array(), '', FALSE);
		$publicationRepository->expects($this->once())->method('findAll')->will($this->returnValue($allPublications));
		$this->inject($this->subject, 'publicationRepository', $publicationRepository);

		$view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
		$view->expects($this->once())->method('assign')->with('publications', $allPublications);
		$this->inject($this->subject, 'view', $view);

		$this->subject->listAction();
	}

	/**
	 * @test
	 */
	public function showActionAssignsTheGivenPublicationToView() {
		$publication = new \UNIVIE\UniviePure\Domain\Model\Publication();

		$view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
		$this->inject($this->subject, 'view', $view);
		$view->expects($this->once())->method('assign')->with('publication', $publication);

		$this->subject->showAction($publication);
	}
}
