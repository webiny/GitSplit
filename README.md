GitSplit Tool
===================

This tool is used to semi-automate the management of read-only git repositories,
that depend on the provided parent repository.

## Installation

```
composer --global require webiny/github-subtree-tool
```

## Running the tool

Just run the following command from the terminal, and follow the procedure:
```
$ cd vendor/webiny/github-subtree-tool/
$ php cli.php
```
## Requirements

### Configuration

GIT_ACC
- name of the github account that holds the repos
- example: Webiny (refers to https://github.com/Webiny)

GIT_REPO
- name of your `master` repo
- example: Framework (refers to https://github.com/Webiny/Framework)

GIT_USER
- your github username
- should have write access to GIT_REPO

GIT_PASS
- github password for GIT_ACC

GIT_SUBTREE
- path where the subtree components are located, inside the `master` repo
- example: src/Webiny/Components

### Structure and repo names

On of the most important requirements is that the component repo names match the ones inside the GIT_SUBTREE path on the parent repo.

For example: https://github.com/Webiny/Framework/tree/master/src/Webiny/Component/**Annotations**
matches: https://github.com/Webiny/**Annotations**


## The back story behind the tool

Let's take our Webiny Framework for example.

The [Framework](https://githuh.com/webiny/framework) repo consists of several components like `Storage`, `Entity`, `Mailer` and others.

These components reside under the `Framework` repo inside `src/Webiny/Component`.

But these components also have their own github repositories:

- https://github.com/Webiny/Storage
- https://github.com/Webiny/Entity
- https://github.com/Webiny/Mailer

The reason for this is that this is a modular framework, meaning you can use
any of the components without actually using the whole framework. And today, the best way to install a PHP component is over composer, meaning you need to have a separate repo for each of the components.

We always do our development inside the `Framework` repo, meaning over time we need to sync the changes to the component repos, including creating branches, releases and updating the composer.json file.

We have created this tool to automate that work.

## How it works

It first checks out the defined branch from your parent repository, in our case that's the `Framework` repo.

Then it checks which components are contained inside that repo, under the define path, in our case that’s the `src/Webiny/Components`.

After that, it checks out the component branch and copies over the changes from the parent repo.

In the end, it creates the requested branch or tag, on both the component repos and the parent repo.
Optionally, it can also update the composer.json files

## Safety

This tool communicates with your github repo, so make sure you understand what you are doing.

Just to be safe, the tool will at the very end of the process ask for your confirmation to confirm that you want to push all the changes. Until that confirmation, no changes are done on the repo.

## Bugs and improvements

Just report them under issues, or even better, send a pull request :)
