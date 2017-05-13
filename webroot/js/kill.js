var getParts = window.location.search.substr(1).split("&");
var GET = {};
for(var i=0; i<getParts.length;i++) {
	var t=getParts[i].split("=");
	GET[decodeURIComponent(t[0])]=decodeURIComponent(t[1]);
}

var commentFormInput = null;
var commentFormSubmitText = null;
var commentLocked = false;

document.addEventListener('DOMContentLoaded', function()
{
	commentFormInput = document.getElementById('comment-form-inputarea');
	if (commentFormInput)
	{
		if (commentFormInput.value.length > 0)
			commentFormInput.nextElementSibling.style.display = 'none';
		commentFormInput.onkeyup = function(e) { updateCommentSubmitText(); if (e.keyCode == 13 && e.ctrlKey) submitComment(); };
		commentFormInput.onblur = updateCommentSubmitText;
		commentFormSubmitText = document.getElementById('comment-form-submit');
		commentFormSubmitText.onclick = function() { if (this.className != 'submit') return; submitComment(); };
		updateCommentSubmitText();
	}
	
	var expandAttackersButton = document.getElementById('attackers-expand-link');
	if (expandAttackersButton)
		expandAttackersButton.onclick = function() { document.getElementById('attackers').className = 'panel'; };
});

function updateCommentSubmitText()
{
	if (!commentFormSubmitText) return;
	if (commentFormSubmitText.overrideText)
	{
		commentFormSubmitText.className = commentFormSubmitText.overrideClass;
		commentFormSubmitText.textContent = commentFormSubmitText.overrideText;
		return;
	}
	
	if (commentFormInput.value.length == 0)
	{
		commentFormSubmitText.className = 'hidden';
		return;
	}
	
	commentFormSubmitText.textContent = 'Submit';
	commentFormSubmitText.className = 'submit';
}
var removeOverrideSubmitText = function()
{
	if (!commentFormSubmitText) return;
	commentFormSubmitText.overrideClass = commentFormSubmitText.overrideText = undefined;
	updateCommentSubmitText();
};
var commentSubmitTextOverrideClearTimeout = 0;
var setOverrideSubmitText = function(className, textContent, duration)
{
	if (!commentFormSubmitText) return;
	commentFormSubmitText.overrideClass = className;
	commentFormSubmitText.overrideText = textContent;
	
	if (commentSubmitTextOverrideClearTimeout)
		window.clearTimeout(commentSubmitTextOverrideClearTimeout);
	
	if (duration > 0)
		commentSubmitTextOverrideClearTimeout = window.setTimeout(removeOverrideSubmitText,duration);
	
	updateCommentSubmitText();
}

var onCommentError = function()
{
	commentLocked = false;
	setOverrideSubmitText('error','SUBMIT FAILED',5000);
};
var onCommentSuccess = function()
{
	if (this.responseText == 'ok')
	{
		commentLocked = false;
		setOverrideSubmitText('success','COMMENT OK',2000);
		
		var commentEntryNode = document.createElement('div');
		commentEntryNode.className = 'comment-entry';
		
		var commenterAvatarNode = document.createElement('div');
		commenterAvatarNode.className = 'comment-commenter-avatar';
		commenterAvatarNode.innerHTML = document.getElementById('comment-form-avatar').innerHTML;
		commentEntryNode.appendChild(commenterAvatarNode);
		
		var commenterUserNameNode = document.createElement('div');
		commenterUserNameNode.className = 'comment-commenter-name';
		commenterUserNameNode.textContent = document.getElementById('comment-form-username').textContent;
		// @todo link
		var commentDateNode = document.createElement('div');
		commentDateNode.className = 'comment-date';
		var now = new Date();
		var fill = function(n) { if (n < 10) return '0' + n; else return ''+n; };
		commentDateNode.textContent = '' + now.getUTCFullYear() + '-' + fill(now.getUTCMonth()+1) + '-' + fill(now.getUTCDate()) + ' ' + fill(now.getUTCHours()) + ':' + fill(now.getUTCMinutes()) + ':' + fill(now.getUTCSeconds());
		commenterUserNameNode.appendChild(commentDateNode);
		commentEntryNode.appendChild(commenterUserNameNode);
		
		var commentTextNode = document.createElement('div');
		commentTextNode.className = 'comment-text';
		commentTextNode.textContent = this.commentText;
		commentEntryNode.appendChild(commentTextNode);
		
		var commentList = document.getElementById('comments-wrapper');
		if (commentList) // there are already comments - find the first and insert before it
		{
			var commentNodes = commentList.children;
			for (var i=0; i < commentNodes.length; ++i)
				if (commentNodes[i].className == commentEntryNode.className)
				{
					commentList.insertBefore(commentEntryNode,commentNodes[i]);
					break;
				}
		}
		else
		{
			var noComments = document.getElementById('no-comments');
			noComments.id = 'comments-wrapper';
			
			var i = 0;
			while (i < noComments.childNodes.length)
			{
				var e = noComments.childNodes[i];
				if (
					(e.nodeName.toLowerCase() != 'div') ||
					(e.id != 'comment-form')
				)
					noComments.removeChild(e);
				else
					++i;
			}
			noComments.appendChild(commentEntryNode);
		}
		
		commentFormInput.value = '';
	}
	else
		onCommentError();
};

function submitComment()
{
	if (commentLocked) return;
	if (!commentFormInput) return;
	
	var commentText = commentFormInput.value;
	if (commentText.length == 0)
	{
		setOverrideSubmitText('error','COMMENT EMPTY',5000);
		return;
	}
	
	commentLocked = true;
	setOverrideSubmitText('status','SUBMITTING...',0);
	
	var xhr = new XMLHttpRequest();
	xhr.open('POST','doComment.php',true);
	xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	var c = 'killID=' + GET.killID + '&comment='+encodeURIComponent(commentText);
	xhr.setRequestHeader('Content-Length',c.length);
	xhr.onload = onCommentSuccess;
	xhr.onerror = onCommentError;
	xhr.commentText = commentText;
	xhr.send(c);
}