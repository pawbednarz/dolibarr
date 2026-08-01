<?php
/* Copyright (C) 2011-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2024-2026	MDW						<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 *
 * $elementype must be defined.
 */

/**
 *	\file			htdocs/core/actions_comments.inc.php
 *  \brief			Code for actions on comments pages
 */
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 *
 * @var string $contextpage
 * @var ?string $action
 * @var int $withproject
 * @var int $idcomment
 * @var int $id
 * @var int $permissiontocomment
 */

// Next should be define in the including php source file
'
@phan-var-force ?string $action
@phan-var-force int $withproject
@phan-var-force int $idcomment
@phan-var-force int $id
@phan-var-force int $permissiontocomment
';

require_once DOL_DOCUMENT_ROOT.'/core/class/comment.class.php';

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$comment = new Comment($db);

// Security.
// Comment::fetch(), ::update() and ::delete() select on the row id alone: no element, no entity and
// no author. So $idcomment coming from the request otherwise designates any comment of the database.
// The including page has already fetched and authorized $object, so every action below is bound to
// that object, and edit/delete are restricted to the author or an admin, which is the rule the
// template core/tpl/bloc_comment.tpl.php applies when it decides to draw the buttons.
$commentelementtype = (isset($object) && is_object($object) && !empty($object->element)) ? $object->element : '';

/**
 * Tell if the current user may modify or remove a comment he loaded.
 *
 * @param	Comment			$comment			The comment, already fetched
 * @param	CommonObject	$object				The object the page is authorized for
 * @param	string			$commentelementtype	Expected element_type
 * @return	bool								True if the comment belongs to $object and to the user
 */
function canEditComment($comment, $object, $commentelementtype)
{
	global $conf, $user;

	if (empty($comment->id) || empty($commentelementtype)) {
		return false;
	}
	if ($comment->element_type != $commentelementtype || $comment->fk_element != $object->id) {
		return false;	// Comment of another record, possibly of another module
	}
	if ($comment->entity != $conf->entity) {
		return false;	// Comment of another entity
	}

	return ($comment->fk_user_author == $user->id) || !empty($user->admin);
}

/*
 * Actions
 */

if ($action == 'addcomment' && !empty($permissiontocomment)) {
	$description = GETPOST('comment_description', 'restricthtml');
	if (!empty($description) && !empty($object->id) && $commentelementtype != '') {
		$comment->description = $description;
		$comment->datec = dol_now();
		$comment->fk_element = $object->id;			// Not the raw request value: $object is the record the page was authorized for
		$comment->element_type = $commentelementtype;
		$comment->fk_user_author = $user->id;
		$comment->entity = $conf->entity;
		if ($comment->create($user) > 0) {
			setEventMessages($langs->trans("CommentAdded"), null, 'mesgs');
			header('Location: '.$varpage.'?id='.$id.($withproject ? '&withproject=1' : ''));
			exit;
		} else {
			setEventMessages($comment->error, $comment->errors, 'errors');
			$action = '';
		}
	}
}
if ($action === 'updatecomment' && !empty($permissiontocomment)) {
	if ($comment->fetch($idcomment) > 0 && canEditComment($comment, $object, $commentelementtype)) {
		$comment->description = GETPOST('comment_description', 'restricthtml');
		if ($comment->update($user) > 0) {
			setEventMessages($langs->trans("CommentAdded"), null, 'mesgs');
			header('Location: '.$varpage.'?id='.$id.($withproject ? '&withproject=1#comment' : ''));
			exit;
		} else {
			setEventMessages($comment->error, $comment->errors, 'errors');
			$action = '';
		}
	}
}
if ($action == 'deletecomment' && !empty($permissiontocomment)) {
	if ($comment->fetch($idcomment) > 0 && canEditComment($comment, $object, $commentelementtype)) {
		if ($comment->delete($user) > 0) {
			setEventMessages($langs->trans("CommentDeleted"), null, 'mesgs');
			header('Location: '.$varpage.'?id='.$id.($withproject ? '&withproject=1' : ''));
			exit;
		} else {
			setEventMessages($comment->error, $comment->errors, 'errors');
			$action = '';
		}
	}
}
