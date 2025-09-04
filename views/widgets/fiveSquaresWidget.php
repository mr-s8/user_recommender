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
        <div id="userSuggestionsTitle"></div>
        <?php if ($showSurveyInfo): ?>
            <a href="#" class="five-squares-info" title="Mehr Informationen">
                <i class="fa fa-info-circle" style="color:#21A1B3; font-size:16px;"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <div class="user-suggestions-container" id="userSuggestions" data-buffer=''>
                <div class="spinner" id="recommenderUserSpinner">Loading...</div>
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
        <p><a href="<?= Html::encode($surveyButtonLink) ?>" target="_blank" class="btn btn-info"><?= Html::encode($surveyButtonText) ?></a></p>
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

.recommended-user-image-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto;
}

.recommended-user-profile-image {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    display: block;
}

/* 
.recommended-user-profile-image:hover {
    transform: scale(1.05);
}*/

.recommended-highlighted-user {
    border: 2px solid #435f6f;
    box-shadow: 0 0 6px #435f6fb3;
    border-radius: 6px;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.recommended-highlighted-user:hover {
    box-shadow: 0 0 10px #435f6fe6;
    transform: scale(1.05);
}

.recommended-user-options-dropdown {
    position: absolute;
    top: 2px;
    right: 2px;
    z-index: 2;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.recommended-user-image-wrapper:hover .recommended-user-options-dropdown {
    opacity: 1;
    pointer-events: auto;
}

.recommended-user-options-toggle {
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

.recommended-user-options-toggle:hover {
    background: #fff;
    color: #c0392b;
}

.recommended-user-name {
    margin-top: 5px;
    font-size: 12px;
    word-wrap: break-word;
    white-space: normal;
}

#recommenderUserSpinner {
    text-align: center;
    font-size: 14px;
    color: #999;
    margin: 10px auto;
}
</style>

<?php
$this->registerJs(<<<JS

    
        var userRecLogClickUrl = '{$logClickUrl}';

        console.log("log url ", userRecLogClickUrl)
        

        var userRecremoveUrl = '{$removeUrl}';

        console.log("reject url ", userRecremoveUrl)
        var UserRecommendationsUrl = '{$recommendationsUrl}';

        var userRecContainer = $('#userSuggestions');
        var userRecSpinner = $('#recommenderUserSpinner');
        var userRecBuffer = []; 
        var userRecHighlightIds = []; 

        $.ajaxSetup({
            data: {
                [yii.getCsrfParam()]: yii.getCsrfToken()
            }
        });

        function createUserElement(user) {
            var displayName = truncateName(user.name, 15);
            return (
                '<div class="user-suggestion-item text-center" data-user-id="' + user.id + '" data-generation-id="' + user.generation_id + '">' +
                    '<div class="recommended-user-image-wrapper">' +
                        '<div class="dropdown recommended-user-options-dropdown">' +
                            '<a class="dropdown-toggle recommended-user-options-toggle" data-toggle="dropdown" href="#" title="Mehr Optionen">' +
                                '<i class="fa fa-cog"></i>' +
                            '</a>' +
                            '<ul class="dropdown-menu dropdown-menu-right">' +
                                '<li><a href="#" class="not-interested" data-user-id="' + user.id + '">Nicht interessiert</a></li>' +
                            '</ul>' +
                        '</div>' +
                        '<a href="' + user.url + '" class="profile-url" data-pjax="0">' +
                            '<img class="img-rounded recommended-user-profile-image" src="' + user.image + '" width="60" height="60" alt="' + user.name + '">' +
                        '</a>' +
                    '</div>' +
                    '<div class="recommended-user-name">' + displayName + '</div>' +
                '</div>'
            );
        }

        function appendUser(user) {
            var el = $(createUserElement(user));
            var img = el.find('.recommended-user-profile-image');

            if (userRecHighlightIds.includes(user.id)) {
                var tooltipText = "Viele Gemeinsamkeiten";
                img.addClass('recommended-highlighted-user')
                   .attr('title', tooltipText)
                   .attr('data-toggle', 'tooltip')
                   .attr('data-placement', 'top');
            }

            userRecContainer.append(el);

            if (img.is('[data-toggle="tooltip"]') && typeof $.fn.tooltip === 'function') {
                img.tooltip({ container: 'body' });
            }
        }

        function truncateName(name, maxLength = 15) {
            if (name.length > maxLength) {
                return name.substring(0, maxLength - 1) + '…';
            }
            return name;
        }

        $.get(UserRecommendationsUrl, function(data) {
            userRecSpinner.remove();
            
             var words = data.title.split(' ');
            if (words.length > 0) {
                var formattedTitle = '<strong>' + words[0] + '</strong>';
                if (words.length > 1) {
                    formattedTitle += ' ' + words.slice(1).join(' ');
                }
                $('#userSuggestionsTitle').html(formattedTitle);
            } else {
                $('#userSuggestionsTitle').text(data.title);
            }

            userRecHighlightIds = data.highlight || [];
            userRecHighlightIds = userRecHighlightIds.map(id => parseInt(id, 10));
            userRecBuffer = data.users.slice(data.toDisplayCount);
            data.users.slice(0, data.toDisplayCount).forEach(appendUser);
        });

        userRecContainer.on('click', '.not-interested', function(e) {
            e.preventDefault();
            var item = $(this).closest('.user-suggestion-item');
            var userId = parseInt(item.data('user-id'), 10);
            var generationId = item.data('generation-id');

            item.fadeOut(300, function() {
                item.remove();
                $.post(userRecremoveUrl, { userId, generationId, highlighted: userRecHighlightIds.includes(userId) ? 1 : 0 });

                if (userRecBuffer.length > 0) {
                    appendUser(userRecBuffer.shift());
                }
            });
        });

        userRecContainer.on('click', '.user-suggestion-item img', function(e) {
            var item = $(this).closest('.user-suggestion-item');
            var userId = parseInt(item.data('user-id'), 10);
            var generationId = item.data('generation-id');


            console.log("inside log click function")
            console.log(userRecLogClickUrl)
            $.post(userRecLogClickUrl, { userId, generationId, highlighted: userRecHighlightIds.includes(userId) ? 1 : 0 });
        });

        userRecContainer.on('mouseleave', '.recommended-user-image-wrapper', function() {
            var dropdown = $(this).find('.dropdown');
            if (dropdown.hasClass('open')) {
                dropdown.removeClass('open');
            }
        });

        var showSurveyInfo = {$jsShowSurveyInfo};
        if (showSurveyInfo) {
            $('.five-squares-info').on('click', function(e) {
                e.preventDefault();
                $('#fiveSquaresSurveyModal').modal('show');
            });
        }




JS);
?>
