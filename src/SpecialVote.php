<?php
class SpecialVote extends SpecialPage {
	function __construct() {
		parent::__construct('Vote');
	}
	
	function handleVoteSubmission() {
		global $wgElectionCandidates, $wgElectionId;
		
		$request = $this->getRequest();
		$output = $this->getOutput();
		
		$votes = $request->getArray('candidateRank');
		$voteRepo = new ElectionVoteRepository(__METHOD__, $wgElectionId);
		$message = $voteRepo->addVotes($this->getUser(), $votes);
		
		if ($message) {
			echo $message; die;
		}
		
		$output->addHTML('Thank you for voting');
	}
	
	function showElectionInactivePage() {
		$output = $this->getOutput();
		
		$output->addHTML('There is no election');
	}
	
	function showAlreadyVotedPage() {
		$output = $this->getOutput();
		
		$output->addHTML('You have already voted.');
	}
	
	function showBlockedPage() {
		$output = $this->getOutput();
		
		$output->addHTML('You are blocked and cannot vote.');
	}
	
	function showUserTooNewPage() {
		$output = $this->getOutput();
		
		$output->addHTML('Your account was created too recently.');
	}
	
	function showVoteForm() {
		global $wgElectionCandidates;
		$output = $this->getOutput();
				
		$numCandidates = sizeof($wgElectionCandidates);
		
		$output->addHTML(Html::openElement('form', ['method' => 'post', 'enctype' => 'multipart/form-data', 'action' => ''])); // TODO: get the action right
		
		$output->addHTML(Html::openElement('table'));
		
		$output->addHTML(Html::openElement('tr'));
		$output->addHTML(Html::element('th', ['scope' => 'row']));
		for ($rankIdx = 1; $rankIdx <= $numCandidates; $rankIdx++) {
			$output->addHTML(Html::element('th', ['scope' => 'col'], $rankIdx));
		}
		$output->addHTML(Html::closeElement('tr'));
		
		foreach ($wgElectionCandidates as $candidateId => $candidateName) {
			$output->addHTML(Html::openElement('tr'));
			$output->addHTML(Html::element('th', ['scope' => 'row'], $candidateName));
			for ($rankIdx = 1; $rankIdx <= $numCandidates; $rankIdx++) {
				$output->addHTML(Html::rawElement('td', [], Html::element('input', ['type' => 'radio', 'name' => 'candidateRank[' . $candidateId . ']', 'value' => $rankIdx])));
			}
			$output->addHTML(Html::closeElement('tr'));
		}
		$output->addHTML(Html::closeElement('table'));
		
		$output->addHTML(Html::element('input', ['type' => 'submit', 'value' => 'Vote']));
		
		$output->addHTML(Html::closeElement('form'));
	}
	
	function showVotePage() {
		global $wgElectionActive, $wgElectionId, $wgElectionMinRegistrationDate;
		
		if (!$wgElectionActive) {
			$this->showElectionInactivePage(); return;
		}
		
		if ($this->getUser()->getBlock()) {
			$this->showBlockedPage(); return;
		}
		
		if (wfTimestamp(TS_UNIX, $this->getUser()->getRegistration()) < $wgElectionMinRegistrationDate) {
			$this->showUserTooNewPage(); return;
		}
		
		$voteLoader = new ElectionVoteLoader(__METHOD__, $wgElectionId);
		if ($voteLoader->hasUserVoted($this->getUser())) {
			$this->showAlreadyVotedPage(); return;
		}
		
		$this->showVoteForm();
	}
	
	function execute($par) {
		$request = $this->getRequest();
		
		if ($request->wasPosted()) {
			$this->handleVoteSubmission();
		} else {
			$this->showVotePage();
		}
	}
}