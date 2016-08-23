<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

use Webiny\GithubSubtreeTool\Lib\Cli;
use Webiny\GithubSubtreeTool\Lib\MasterRepo;
use Webiny\GithubSubtreeTool\Lib\System;

// cli check
if (!php_sapi_name() == "cli") {
    die('You must run this script from your command line');
}

// initial requirements
require_once 'config.php';
require_once 'vendor/autoload.php';

// temp loader
require_once 'lib/Cli.php';
require_once 'lib/AbstractRepo.php';
require_once 'lib/MasterRepo.php';
require_once 'lib/SubtreeRepo.php';
require_once 'lib/System.php';

// repo dir
define('REPO_DIR', __DIR__ . '/repos/'); // if changed, make sure you have a trailing slash at the end

// start time
$startTime = System::getTime();

// initialize the cli
$cli = new Cli();

// print header
$cli->header();

// start the interaction
$cli->prompt('Press any key to continue...', 'ENTER', '');

// create a fresh repo dir
System::removeResource(REPO_DIR . '*');
System::createDir(REPO_DIR);

// confirm with the user the repo
$cli->line('The tool will now gather data about your root repo (' . $cli->colorHighlight() . GIT_ACC . '/' . GIT_REPO . $cli->colorEnd() . ').');
$cli->prompt('Press any key to continue...', 'ENTER', '');

// init Repo instance for master repo
$masterRepo = new MasterRepo(GIT_REPO, 'master');

// get the tag and branch info
$cli->line('...(please wait)');
$branches = $masterRepo->getBranches();
$latestBranch = $masterRepo->getLatestBranch();
$latestTag = $masterRepo->getLatestTag();

// output info
$cli->separator();
$cli->line('Latest branch: ' . $latestBranch);
$cli->line('Latest tag: ' . $latestTag);

// ask the user what does he want to do
$cli->separator();
$menu = [
    '1' => 'Branch',
    '2' => 'Tag',
];
$choice = $cli->menu($menu, null, 'What do you want to delete?');
$branchOrTag = ($choice === 1) ? 'branch' : 'tag';

// get the next release version
if ($branchOrTag == 'tag') {
    $versionData = explode('.', $latestTag);
    $toDelete = $versionData[0] . '.' . $versionData[1] . '.' . ($versionData[2]);
} else {
    $toDelete = $latestBranch;
}


while (true) {
    $releaseToDelete = $cli->prompt('Type in the ' . strtoupper($branchOrTag) . ' number you wish to delete',
        $toDelete);

    // validate the next release
    if ($branchOrTag == 'tag' && preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $releaseToDelete)) {
        $releaseToDelete = 'v' . $releaseToDelete;
        break;
    } else if ($branchOrTag == 'branch' && preg_match('/(\d{1,10}\.\d{1,10})/', $releaseToDelete)) {
        break;
    }
}

// extract the subtree components
$cli->line('...(please wait)');
$subTrees = $masterRepo->getSubtreeComponents();

// display the subtree components
$cli->line('For the following components the ' . $cli->colorHighlight() . strtoupper($branchOrTag) . ' ' . $releaseToDelete . $cli->colorEnd() . ' will be deleted:');

$rows = [];
foreach ($subTrees as $st) {
    $rows[] = [
        $st->getRepo(),
        $st->getLatestBranch(),
        $st->getLatestTag(),
        $st->getBranch()
    ];
}
$headers = [
    'Component',
    'Latest branch',
    'Latest tag',
    'Working branch'
];
$cli->table($headers, $rows);
$cli->line('NOTE: master branch is selected on a component, if there is no matching source branch');
$cli->prompt('Press any key to continue...', 'ENTER', '');

// clone the master repo branch
$cli->separator();
$cli->line('Cloning ' . $masterRepo->getRepo() . ' (' . $masterRepo->getBranch() . ')');
$masterRepo->cloneRepo();
$masterRepo->checkoutExistingBranch($releaseToDelete);
$masterRepo->checkoutExistingBranch('master');

// create the new branch/tag on master repo
$cli->line('Deleting ' . $branchOrTag . ' ' . $releaseToDelete);
if ($branchOrTag == 'branch') {
    $masterRepo->deleteBranch($releaseToDelete);
} else {
    $masterRepo->deleteTag($releaseToDelete);
}

// clone the subtree repos
foreach ($subTrees as $st) {
    $cli->separator();
    $cli->line($cli->colorHighlight() . 'Updating ' . strtoupper($st->getRepo()) . ' component' . $cli->colorEnd());

    // clone repo
    $st->cloneRepo();
    $st->checkoutExistingBranch($releaseToDelete);
    $st->checkoutExistingBranch('master');

    // create the release
    if ($branchOrTag == 'branch') {
        $st->deleteBranch($releaseToDelete);
    } else {
        $st->deleteTag($releaseToDelete);
    }
}


// ask the confirmation before the push
$cli->separator();
$menu = [
    1 => 'Push changes',
    2 => 'Cancel',
];
$choice = $cli->menu($menu, null, 'All the changes are ready, please confirm your action');
if ($choice === 2) {
    $cli->line('Action canceled.');
    die();
}

$cli->line($cli->colorHighlight() . 'Pushing changes: ' . GIT_ACC . '/' . GIT_REPO . $cli->colorEnd());


// commit the changes
$masterRepo->pushTagDelete($releaseToDelete);

// push the changes for subtrees
foreach ($subTrees as $st) {
    $cli->line($cli->colorHighlight() . 'Pushing changes: ' . GIT_ACC . '/' . $st->getRepo() . $cli->colorEnd());

    // if we created a new branch, we also need a new tag for it
    $st->pushTagDelete($releaseToDelete);
}

// all done
$cli->separator();
$cli->line($cli->colorHighlight() . 'All done!' . $cli->colorEnd());
$cli->line('Execution time: ' . (System::displayTime(System::getTime() - $startTime)));
$cli->separator();