<script type="text/javascript" src="<?php echo $Campsite['WEBSITE_URL']; ?>/js/json2.js"></script>

<script type="text/javascript">
// Print last modified date
var dateTime = '<?php if ($savedToday) { p(date("H:i:s", $lastModified)); } else { p(date("Y-m-d H:i", $lastModified)); } ?>';
var fullDate = '<?php p(date("Y-m-d H:i:s", $lastModified)); ?>';
document.getElementById('info-text').innerHTML = '<?php putGS('Saved'); ?> ' + ' ' + dateTime;
document.getElementById('date-last-modified').innerHTML = '<?php putGS('Last modified'); ?> ' + ': ' + fullDate;

/**
 * Close window after timeout
 * @param int timeout
 * @return void
 */
var close = function(timeout) {
    setTimeout("window.location.href = '<?php
    if ($f_publication_id > 0 && $f_issue_number > 0 && $f_section_number > 0) {
    	echo "/$ADMIN/articles/index.php?f_publication_id=$f_publication_id&f_issue_number=$f_issue_number&f_language_id=$f_language_id&f_section_number=$f_section_number";
    } else {
    	echo "/$ADMIN/";
    }
    ?>'", timeout);
};

$(function() {

// make breadcrumbs + save buttons sticky
$('.breadcrumb-bar, .toolbar').wrapAll('<div class="sticky" />');

// datepicker for date
$('.datepicker').datepicker({
    dateFormat: 'yy-mm-dd'
});

// accordion hovers
$('.ui-accordion-header').hover(
    function(){ $(this).removeClass('ui-state-default').addClass('ui-state-hover'); },
    function(){ $(this).removeClass('ui-state-hover').addClass('ui-state-default'); }
);

// hover states on the static widgets
$('.icon-button').hover(
    function() { $(this).addClass('ui-state-hover'); },
    function() { $(this).removeClass('ui-state-hover'); }
);

$('.collapsible').each(function(index) {
    var head = $('> .head', $(this));
    var cookie = 'articlebox-' + index;
    var closed = $.cookie(cookie);
    var expires = { expires: 14 } // 14 days cookie expiration

    // init by cookie
    if (closed != 1) {
        head.addClass('ui-state-active');
    } else {
        $(this).next().hide();
    }

    // toggle
    $(this).click(function() {
        $(this).next().toggle('fast');
        head.toggleClass('ui-state-active');
        if (head.hasClass('ui-state-active')) {
            $.cookie(cookie, 0, expires);
        } else {
            $.cookie(cookie, 1, expires);
        }
        return false;
    });
});

// copy title to hidden field
$('input:text[name=f_article_title]').change(function() {
    $('input:hidden[name=f_article_title]').val($(this).val())
        .closest('form').change();
}).change();

/**
 * Enable/disable comments list/form according to selected state.
 */
var toggleComments = function() {
    $('input:radio[name^="f_comment"]:checked').each(function() {
        var form = $('#comments-form');
        var list = $('#comments-list');
        var commentReply = $('#comment-moderate dd.buttons');
        switch ($(this).val()) {
            case 'enabled':
                form.show();
                list.show();
                commentReply.show();
                break;

            case 'disabled':
                form.hide();
                list.hide();
                break;

            case 'locked':
                form.hide();
                list.show();
                commentReply.hide();
                break;
        }
    });
};

// init
toggleComments();

/**
 * Telling to the Tinymce that the current state is the correct one
 */
cleanTextContents = function()
{
    var editor_rank = 0;
    while (true) {
        var editor_obj = tinyMCE.get(editor_rank);
        if (!editor_obj) {
            break;
        }
		editor_obj.isNotDirty = true;
        editor_rank += 1;
    }
};

/**
 * Tracking save problems is used at checking un/saved state of article
 */
window.save_had_problems = false;
window.ajax_had_problems = false;

// main form submit
$('form#article-main').submit(function() {

	window.save_had_problems = false;
    var form = $(this);

    if (!articleChanged()) {
        flashMessage('<?php putGS('Article saved.'); ?>');
    } else {
		// tinymce should know that the current state is the correct one
		cleanTextContents();

    	 // ping for connection
        callServer('ping', [], function(json) {
            $.ajax({
                type: 'POST',
                url: '<?php echo $Campsite['WEBSITE_URL']; ?>/admin/articles/post.php',
                data: form.serialize(),
                success: function(data, status, p) {
                    flashMessage('<?php putGS('Article saved.'); ?>');
                    toggleComments();
                },
                error: function (rq, status, error) {
					window.save_had_problems = true;
                    if (status == 0 || status == -1) {
                        flashMessage('<?php putGS('Unable to reach Newscoop. Please check your internet connection.'); ?>', 'error');
                    }
                }
            });

        }); // /ping
        $(this).removeClass('changed');
    }

    return false;
}).change(function() {
    $(this).addClass('changed');
});

/**
 * Unlock article
 * @return void
 */
var unlockArticle = function() {
    callServer(['Article', 'setIsLocked'], [
        <?php echo $f_language_selected; ?>,
        <?php echo $articleObj->getArticleNumber(); ?>,
        0,
        <?php echo $g_user->getUserId(); ?>]);
};

<?php if ($inEditMode) { ?>

// save all buttons
$('.save-button-bar input').click(function() {
    $('form#article-keywords').submit();
    $('form#article-switches').submit();
    $('form#article-main').submit();
    
    if ($(this).attr('id') == 'save_and_close') {
		unlockArticle();
		$(this).ajaxComplete(function() {
            close(1500);
        });
    }

    return false;
});

<?php } else { // view mode ?>
$('.save-button-bar input#save_and_close').click(function() {
<?php if ($articleObj->isLocked() && $articleObj->getLockedByUser() == $g_user->getUserId()) { ?>
    unlockArticle();
<?php } ?>
    close(1);
});
<?php } ?>



var authorsList = [
<?php
$allAuthors = Author::GetAllExistingNames();
$quoteStringFn = create_function('&$value, $key',
    '$value = json_encode((string) $value);');
array_walk($allAuthors, $quoteStringFn);
echo implode(",\n", $allAuthors);
?>
];

// authors autocomplete
$(".aauthor").autocomplete({
    source: authorsList
});
$(".aauthor").live('focus', function() {
    $(".aauthor").autocomplete({
        source: authorsList
    });
});

// fancybox for popups
$('a.iframe').each(function() {
    if (!$(this).attr('custom')) {
        $(this).fancybox({
            hideOnContentClick: false,
            width: 660,
            height: 500,
            onStart: function() { // check if there are any changes
                return checkChanged();
            },
            onClosed: function(url, params) {
                if ($.fancybox.reload) { // reload if set
                    if ($.fancybox.message) { // set message after reload
                        $.cookie('flashMessage', $.fancybox.message);
                    }
                    window.location.reload();
                } else if ($.fancybox.error) {
                    flashMessage($.fancybox.error, 'error');
                }
            }
        });
    }
});
$('#locations_box a.iframe').each(function() {
    $(this).data('fancybox').showCloseButton = false;
    $(this).data('fancybox').width = 1100;
    $(this).data('fancybox').height = 660;

});

// comments form check for changes
$('form#article-comments').submit(function() {
    if (!checkChanged()) {
        return false;
    }
});

var message = $.cookie('flashMessage');
if (message) {
    flashMessage(message);
    $.cookie('flashMessage', null);
}

}); // /document.ready

/**
 * Check for unsaved changes in tinymce editors
 * @return bool
 */
function editorsChanged()
{
    var editor_rank = 0;
    while (true) {
        var editor_obj = tinyMCE.get(editor_rank);
        if (!editor_obj) {
            break;
        }
        if (editor_obj.isDirty()) {
            return true;
        }
        editor_rank += 1;
    }

    return false;
};

/**
 * Check for unsaved changes in main/boxes forms
 * @return bool
 */
function articleChanged()
{
	if (window.save_had_problems || window.ajax_had_problems) {
		return true;
	}

    if ((!editorsChanged()) && ($('form.changed').size() == 0)) {
		return false;
	}

	return true;
};

window.article_confirm_question = '<?php putGS('Your work has not been saved. Do you want to continue and lose your changes?'); ?>';

/**
 * Check for unsaved changes in main/boxes forms
 * Asks for confirmations too.
 * @return bool
 */
function checkChanged()
{
    if( $("#f_action_workflow").val() == 'N' ) {
        <?php
            if ( count($articleEvents) ) {
                ?>
                return confirm('<?php putGS('Please be aware that all scheduled publishing events for this article will be deleted when you set this article to "New" state. Please confirm the state change.'); ?>');
                <?php
            }
        ?>
    }
    if (!articleChanged()) {
        return true; // continue
    }
    return confirm(window.article_confirm_question);
}

/**
 * Check for unsaved changes in main/boxes forms
 * Warn if leaving the page without saving it.
 */
$(document).ready(function() {
    window.onbeforeunload = function ()
    {
        if (articleChanged())
        {
            return window.article_confirm_question;
        }
    };
});

</script>
