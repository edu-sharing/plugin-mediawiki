
var customizeToolbar = function () {

    $.wikiEditor.modules.dialogs.modules['eduSharing'] = {
        title: mw.messages.values['wikieditor-toolbar-edusharing-title'],
        id: 'wikieditor-toolbar-edusharing-dialog',
        html: '\
                        <iframe id="edusharing" src=""></iframe>\
<div id="wikieditor-toolbar-edu-form"><fieldset>\
                            <input type="hidden" id="origImageRatio" value="0" />\
                            <input type="hidden" id="wikieditor-toolbar-edu-mimetype" value=""/>\
<input type="hidden" id="wikieditor-toolbar-edu-repotype" value=""/>\
<input type="hidden" id="wikieditor-toolbar-edu-version" value=""/>\
<div class="wikieditor-toolbar-field-wrapper">\
                                <label class="edusharing-dialog" for="wikieditor-toolbar-edu-object">'+ mw.messages.values['wikieditor-toolbar-edusharing-object'] + '</label>\
                                <input type="text" id="wikieditor-toolbar-edu-object" disabled="disabled"/>\
                                <button onclick="showEduFrame()">'+ mw.messages.values['wikieditor-toolbar-edusharing-search'] + '</button>\
                            </div>\
                            <div class="wikieditor-toolbar-field-wrapper">\
                                <label class="edusharing-dialog" for="wikieditor-toolbar-edu-caption">'+ mw.messages.values['wikieditor-toolbar-edusharing-caption'] + '</label>\
                                <input type="text" id="wikieditor-toolbar-edu-caption"/>\
                            </div>\
                            <div class="wikieditor-toolbar-edu-options">\
                                        <div class="wikieditor-toolbar-field-wrapper">\
                                            <label class="edusharing-dialog" for="wikieditor-toolbar-edu-versionShow">'+ mw.messages.values['wikieditor-toolbar-edusharing-version'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-versionShow" value="latest" checked="checked"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-version-latest'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-versionShow" value="current"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-version-current'] + '</label>\
                                        </div>\
                                        <div class="wikieditor-toolbar-field-wrapper">\
                                            <label class="edusharing-dialog" for="wikieditor-toolbar-edu-float">'+ mw.messages.values['wikieditor-toolbar-edusharing-float'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-float" value="left"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-float-left'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-float" value="none" checked="checked"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-float-none'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-float" value="right"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-float-right'] + '</label>\
                                            <input type="radio" class="edusharing-dialog" name="wikieditor-toolbar-edu-float" value="inline"><label class="edusharing-dialog radio">'+ mw.messages.values['wikieditor-toolbar-edusharing-float-inline'] + '</label>\
                                        </div>\
                                        <div class="wikieditor-toolbar-field-wrapper" id="wikieditor-toolbar-edu-measurements" style="display: none">\
                                            <div id="wikieditor-toolbar-edu-measurements-height"><label class="edusharing-dialog" for="wikieditor-toolbar-edu-height">'+ mw.messages.values['wikieditor-toolbar-edusharing-height'] + '</label>\
                                            <input type="text" id="wikieditor-toolbar-edu-height" onkeyup="setWidth()"/>&nbsp;'+ mw.messages.values['wikieditor-toolbar-edusharing-px'] + '</div>\
                                            <label class="edusharing-dialog" for="wikieditor-toolbar-edu-width">'+ mw.messages.values['wikieditor-toolbar-edusharing-width'] + '</label>\
                                            <input type="text" id="wikieditor-toolbar-edu-width" onkeyup="setHeight()"/>&nbsp;'+ mw.messages.values['wikieditor-toolbar-edusharing-px'] + '\
                                            <div id="wikieditor-toolbar-edu-measurements-proportions"><input type="checkbox" id="wikieditor-toolbar-edu-constrainProportions" value="1" checked="checked"/>&nbsp;' + mw.messages.values['wikieditor-toolbar-edusharing-constrainPropoertions'] + '</div>\
                                        </div>\
                                </div>\
                            </div>\
                        </fieldset></div>\
                        <div id="wikieditor-toolbar-edu-preview">'
            + getPreviewText() +
            '<div id="wikieditor-toolbar-edu-preview-res"></div>'
            + getPreviewText() +
            '<div style="clear:both"></div>\
    </div>',
        init: function () {
        },
        dialog: {
            resizable: true,
            dialogClass: 'wikiEditor-toolbar-dialog',
            width: 600,
            height: 400,
            buttons: {
                'wikieditor-toolbar-edusharing-insert': function () {
                    var edu_object, edu_caption, edu_height, edu_width, edu_mimetype, edu_repotype;

                    edu_object = $('#wikieditor-toolbar-edu-object').val();
                    edu_caption = $('#wikieditor-toolbar-edu-caption').val();
                    edu_height = $('#wikieditor-toolbar-edu-height').val();
                    edu_width = $('#wikieditor-toolbar-edu-width').val();
                    edu_repotype = $('#wikieditor-toolbar-edu-repotype').val();
                    edu_mimetype = $('#wikieditor-toolbar-edu-mimetype').val();
                    edu_float = $('[name="wikieditor-toolbar-edu-float"]:checked').val();
                    edu_versionShow = $('[name="wikieditor-toolbar-edu-versionShow"]:checked').val();
                    edu_version = $('#wikieditor-toolbar-edu-version').val();

                    if (edu_width === '' || edu_width === '0')
                        edu_width = '400';
                    
                    if (edu_height === '' || edu_height === '0')
                        edu_height = 'auto';
                    

                    $(this).dialog('close');
                    $.wikiEditor.modules.toolbar.fn.doAction(
                        $(this).data('context'),
                        {
                            type: 'replace',
                            options: {
                                pre: '<edusharing action="new" id="' + edu_object + '" width="' + edu_width + '" height="' + edu_height + '" mimetype="' + edu_mimetype + '" repotype="' + edu_repotype + '"  float="' + edu_float + '" version="' + edu_version + '" versionShow="' + edu_versionShow + '">',
                                peri: edu_caption,
                                post: '</edusharing>',
                                ownline: true
                            }
                        },
                        $(this)
                    );
                    resetForm();
                },
                'wikieditor-toolbar-edusharing-cancel': function () {
                    resetForm();
                    $(this).dialog('close');
                }
            },
            open: function () {
                $('#wikieditor-toolbar-edu-object').focus();
                if (!($(this).data('dialogkeypressset'))) {
                    $(this).data('dialogkeypressset', true);
                    // Execute the action associated with the first button
                    // when the user presses Enter
                    $(this).closest('.ui-dialog').keypress(function (e) {
                        if (e.which === 13) {
                            var button = $(this).data('dialogaction') ||
                                $(this).find('button:first');
                            button.click();
                            e.preventDefault();
                        }
                    });

                    // Make tabbing to a button and pressing
                    // Enter do what people expect
                    $(this).closest('.ui-dialog').find('button').focus(function () {
                        $(this).closest('.ui-dialog').data('dialogaction', this);
                    });
                }

                $('#edusharing').attr('src', mw.config.get('edugui'));

                $('input[name="wikieditor-toolbar-edu-float"]').change(function () {
                    updatePreview($(this).val());
                });

            }
        }
    }

    $('#wpTextbox1').wikiEditor('addToToolbar', {
        'section': 'main',
        'group': 'insert',
        'tools': {
            'edusharing': {
                'label': mw.messages.values['wikieditor-toolbar-edusharing-title'],
                'type': 'button',
                'icon': mw.config.get('eduicon'),
                'action': {
                    'type': 'dialog',
                    'module': 'eduSharing'
                }
            }
        }
    });//END:wikiEditor('addToToolbar')
};

//loader - https://www.mediawiki.org/wiki/Extension_talk:WikiEditor/Toolbar_customization#Icons_for_added_toolbar_buttons
mw.loader.using('user.options',
    function () {
        if (mw.user.options.get('usebetatoolbar')) {
            mw.loader.using('ext.wikiEditor',
                function () {
                    $(document).ready(
                        function () {
                            customizeToolbar();
                        }
                    );
                }
            );
        }
    }
);


function resetForm() {
    // Restore form state
    $(['#wikieditor-toolbar-edu-object',
        '#wikieditor-toolbar-edu-caption',
        '#wikieditor-toolbar-edu-height',
        '#wikieditor-toolbar-edu-width',
        '#wikieditor-toolbar-edu-mimetype',
        '#wikieditor-toolbar-edu-repotype'].join(',')
    ).val('');
    $('#wikieditor-toolbar-edu-constrainProportions').prop('checked', 'checked');
    $('[name="wikieditor-toolbar-edu-float"][value="none"]').prop('checked', 'checked');
    $('[name="wikieditor-toolbar-edu-versionShow"][value="latest"]').prop('checked', 'checked');
    $('#wikieditor-toolbar-edu-preview-res').html('');
    updatePreview('none');
    showEduFrame();
}

window.setHeight = function () {
    if (getOrigImageRatio() == 0 || !$('#wikieditor-toolbar-edu-constrainProportions').prop('checked'))
        return;
    $('#wikieditor-toolbar-edu-height').val(Math.round($('#wikieditor-toolbar-edu-width').val() / getOrigImageRatio()));
}

window.setWidth = function () {
    if (getOrigImageRatio() == 0 || !$('#wikieditor-toolbar-edu-constrainProportions').prop('checked'))
        return;
    $('#wikieditor-toolbar-edu-width').val(Math.round($('#wikieditor-toolbar-edu-height').val() * getOrigImageRatio()));
}

function getOrigImageRatio() {
    return $('#origImageRatio').val();
}

function updatePreview(val) {
    element = $('#wikieditor-toolbar-edu-preview-res');
    switch (val) {
        case 'none': element.css('margin', '10px 0').css('display', 'block').css('float', val); break;
        case 'left': element.css('margin', '10px 10px 10px 0').css('display', 'block').css('float', val); break;
        case 'right': element.css('margin', '10px 0 10px 10px').css('display', 'block').css('float', val); break;
        case 'inline': element.css('margin', '0').css('display', 'inline-block').css('float', 'none'); break;
    }
}

window.showEduFrame = function () {
    $('#edusharing').css('display', 'block');
    $('#edusharing').attr('src', mw.config.get('edugui'));
}

window.hideEduFrame = function () {
    $('#edusharing').css('display', 'none');
}

// window.setData = function (objid, caption, mimetype, width, height, version, repotype) {
//     console.log(objid, caption, mimetype, width, height, version, repotype);

// Receive data from iframe
window.addEventListener('message', receiveMessage, false);
function receiveMessage(event){
    if(event.data.event == 'APPLY_NODE'){
        var node = event.data.data;
        document.getElementById('wikieditor-toolbar-edu-object').value = node.objectUrl;
        document.getElementById('wikieditor-toolbar-edu-caption').value = node.title;
        document.getElementById('wikieditor-toolbar-edu-mimetype').value = node.mimetype;
        document.getElementById('wikieditor-toolbar-edu-repotype').value = node.repositoryType;
        $('#wikieditor-toolbar-edu-version').val(node.content.version);
        document.getElementById('wikieditor-toolbar-edu-width').value = node.preview.width;
        document.getElementById('wikieditor-toolbar-edu-height').value = node.preview.height;
        $('#origImageRatio').val(node.preview.width / node.preview.height);
        window.hideEduFrame();
        mimeSwitchHelper = '';
        if (mimetype.indexOf('image') !== -1)
            mimeSwitchHelper = 'image';
        else if (mimetype.indexOf('audio') !== -1)
            mimeSwitchHelper = 'audio';
        else if (mimetype.indexOf('video') !== -1 || repotype.indexOf('YOUTUBE') !== -1)
            mimeSwitchHelper = 'video';
        else
            mimeSwitchHelper = 'textlike';
        switch (mimeSwitchHelper) {
            case 'image':
                $('#wikieditor-toolbar-edu-measurements-height').show();
                $('#wikieditor-toolbar-edu-measurements-proportions').show();
                $('#wikieditor-toolbar-edu-measurements').slideDown();
                append = '<img src="' + mw.config.get('edupreview') + 'nodeId=' + objid.substr(objid.lastIndexOf('/') + 1) + '&ticket=' + mw.config.get('eduticket') + '" width="80"/>';
                break;
            case 'audio':
                $('#wikieditor-toolbar-edu-measurements').slideUp();
                append = '<img src="' + mw.config.get('edu_preview_icon_audio') + '" height="10px"/>';
                break;
            case 'video':
                $('#wikieditor-toolbar-edu-measurements-height').hide();
                $('#wikieditor-toolbar-edu-measurements-proportions').hide();
                $('#wikieditor-toolbar-edu-measurements').slideDown();
                append = '<img src="' + mw.config.get('edu_preview_icon_video') + '" width="80"/>';
                break;
            case 'textlike':
            default:
                $('#wikieditor-toolbar-edu-measurements').slideUp();
                append = '<span style="color: #00f">' + getPreviewText('short') + '</span>';

        }


        $('#wikieditor-toolbar-edu-preview-res').html('').append(append);
    }
}

function getPreviewText(short) {
    if (short)
        return 'Lorem ipsum dolor';
    return 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.';
}
