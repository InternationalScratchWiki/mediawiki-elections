<?php
class SpecialElectionResults extends SpecialPage {
	function __construct() {
		parent::__construct('ElectionResults', 'viewelectionresults');
	}
	
	function execute($par) {
		global $wgElectionActive, $wgElectionId, $wgElectionCandidates;
		
		$output = $this->getOutput();
		$output->setPageTitle('Election results');
		
		$voteLoader = new ElectionVoteLoader(__METHOD__, $wgElectionId);
		$results = $voteLoader->getResults($wgElectionCandidates);
		
		$output->addHTML(Html::openElement('table'));
		foreach ($results as $candidate => $score) {
			$output->addHTML(Html::openElement('tr'));
			
			$output->addHTML(Html::element('th', ['scope' => 'row'], $candidate));
			$output->addHTML(Html::element('td', [], $score));
			
			$output->addHTML(Html::closeElement('tr'));
		}
		$output->addHTML(Html::closeElement('table'));
	}
}