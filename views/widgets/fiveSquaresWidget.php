<?php
use yii\helpers\Html;
use yii\helpers\Url;


/** @var \humhub\modules\user\models\User[] $users */
/** @var int $toDisplayCount */


// js variables
$jsShowSurveyInfo = $showSurveyInfo ? 'true' : 'false';

?>

<div class="panel panel-default">
    <div class="panel-heading d-flex justify-content-between align-items-center" style="display:flex; justify-content:space-between; align-items:center;">
        <strong id="userSuggestionsTitle"></strong>
        <?php if ($showSurveyInfo): ?>
            <a href="#" class="five-squares-info" title="Mehr Informationen">
                <i class="fa fa-info-circle" style="color:#f1c40f; font-size:16px;"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <div class="user-suggestions-container" id="userSuggestions" data-buffer=''>
                <div class="spinner" id="userSpinner">Loading...</div>
        
        </div>
    </div>
</div>

<!-- Modal -->
<?php if ($showSurveyInfo): ?>
<div class="modal fade" id="fiveSquaresSurveyModal" tabindex="-1" role="dialog" aria-labelledby="fiveSquaresSurveyLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="fiveSquaresSurveyLabel"><?= Html::encode($surveyTitle) ?></h4>
      </div>
      <div class="modal-body">
        <p><?= Html::encode($surveyText) ?></p>
        <br>
        <p><a href="<?= Html::encode($surveyButtonLink) ?>" target="_blank" class="btn btn-warning"><?= Html::encode($surveyButtonText) ?></a></p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>





<style>
.user-suggestions-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 10px;
    max-width: 400px;
    margin: 0 auto;
}

.user-suggestion-item {
    width: 60px;
    max-width: 80px;
    text-align: center;
    position: relative;
    box-sizing: border-box;
}

.user-image-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto;
}


.user-profile-image {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    display: block;
}

.highlighted-user {
    border: 2px solid #f39c12; /* HumHub Orange */
    box-shadow: 0 0 6px rgba(243, 156, 18, 0.7); /* leuchtender Effekt */
    border-radius: 6px; /* gleich wie Profilbilder */
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.highlighted-user:hover {
    box-shadow: 0 0 10px rgba(243, 156, 18, 0.9);
    transform: scale(1.05); /* ganz leichtes Vergrößern beim Hover */
}

/* Dropdown-Icon */
.user-options-dropdown {
    position: absolute;
    top: 2px;
    right: 2px;
    z-index: 2;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.user-image-wrapper:hover .user-options-dropdown {
    opacity: 1;
    pointer-events: auto;
}

/* Symbol-Stil */
.user-options-toggle {
    font-size: 14px;
    color: #e74c3c;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 0 2px rgba(0,0,0,0.2);
    transition: background 0.2s ease;
}

.user-options-toggle:hover {
    background: #fff;
    color: #c0392b;
}

.user-name {
    margin-top: 5px;
    font-size: 12px;
    word-wrap: break-word;
    white-space: normal;
}


#userSpinner {
    text-align: center;
    font-size: 14px;
    color: #999;
    margin: 10px auto;
}







</style>

<?php
$this->registerJs(<<<JS

    const logClickUrl = '{$logClickUrl}';
    const removeUrl = '{$removeUrl}';
    const recommendationsUrl = '{$recommendationsUrl}'

    
    const container = $('#userSuggestions');
    const spinner = $('#userSpinner');
    let buffer = []; 

    let highlightIds = []; 



    $.ajaxSetup({
        data: {
            [yii.getCsrfParam()]: yii.getCsrfToken()
        }
    });


   
    // creating a user element
    function createUserElement(user) {
        return (
            '<div class="user-suggestion-item text-center" data-user-id="' + user.id + '" data-generation-id="' + user.generation_id + '">' +
                '<div class="user-image-wrapper">' +
                    '<div class="dropdown user-options-dropdown">' +
                        '<a class="dropdown-toggle user-options-toggle" data-toggle="dropdown" href="#" title="Mehr Optionen">' +
                            '<i class="fa fa-cog"></i>' +
                        '</a>' +
                        '<ul class="dropdown-menu dropdown-menu-right">' +
                            '<li><a href="#" class="not-interested" data-user-id="' + user.id + '">Nicht interessiert</a></li>' +
                        '</ul>' +
                    '</div>' +
                    '<a href="' + user.url + '" data-pjax="0">' +
                        '<img class="img-rounded user-profile-image" src="' + user.image + '" width="60" height="60" alt="' + user.name + '">' +
                    '</a>' +
                '</div>' +
                '<div class="user-name">' + user.name + '</div>' +
            '</div>'
        );
    }

    // appending a user to the widget
    function appendUser(user) {
        const el = $(createUserElement(user));
        const img = el.find('.user-profile-image');

        if (highlightIds.includes(user.id)) {

            const tooltipText = "Besonders Passend"

            img.addClass('highlighted-user')
            .attr('title', tooltipText)
            .attr('data-toggle', 'tooltip')
            .attr('data-placement', 'top');
        }


        container.append(el);

        // init tooltip after appending; if bootstrap accessible
        if (img.is('[data-toggle="tooltip"]') && typeof $.fn.tooltip === 'function') {
            img.tooltip({ container: 'body' });
        }
    }



    // loading the recommendations
    $.get(recommendationsUrl, function(data) {
        spinner.remove(); // Spinner weg

        $('#userSuggestionsTitle').text(data.title);

        highlightIds = data.highlight || [];
        
        buffer = data.users.slice(data.toDisplayCount); // Rest in Buffer

        // show displaycount recommendations initially
        data.users.slice(0, data.toDisplayCount).forEach(appendUser);
    });

    // logging a click on not interested
    container.on('click', '.not-interested', function(e) {
        e.preventDefault();
        const item = $(this).closest('.user-suggestion-item');
        const userId = $(this).data('user-id');
        const generationId = item.data('generation-id');

        item.fadeOut(300, function() {
            item.remove();
            
            $.post(removeUrl, { userId, generationId, highlighted: highlightIds.includes(userId) ? 1 : 0 });

            // load a new rec from buffer
            if (buffer.length > 0) {
                appendUser(buffer.shift());
            }
        });
    });

    // log clicks on recommendations
    container.on('click', '.user-suggestion-item img', function() {
        const item = $(this).closest('.user-suggestion-item');
        const userId = item.data('user-id');
        const generationId = item.data('generation-id');

        $.post(logClickUrl, { userId, generationId, highlighted: highlightIds.includes(userId) ? 1 : 0 });
        // no preventDefault
    });


    // closing the dropdown menu if mouse leaves a user profile image
    container.on('mouseleave', '.user-image-wrapper', function() {
        const dropdown = $(this).find('.dropdown');
        if (dropdown.hasClass('open')) {
            dropdown.removeClass('open'); // 
        }
    });



    const showSurveyInfo = {$jsShowSurveyInfo}

    
    if (showSurveyInfo) {
    $('.five-squares-info').on('click', function(e) {
        e.preventDefault();
        $('#fiveSquaresSurveyModal').modal('show');
    });
}


    
JS);
?>

