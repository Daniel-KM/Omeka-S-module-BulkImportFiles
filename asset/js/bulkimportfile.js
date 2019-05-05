$(document).ready(function () {


    // available_maps = $.parseJSON($('.bulkimportfile_maps_settings').val());
    //
    // console.log(available_maps);
    //
    //
    // available_maps_html = '<div class="title">BulkImportFile current maps:';
    // available_maps_html += '</div>';
    // available_maps_html += '<div>';
    //
    // $.each(available_maps, function (key, val) {
    //     available_maps_html += '<div class="field js-maps-' + val + '">'+key+' - <a class="button">' + val + '</a></div>';
    // })
    //
    // available_maps_html += '</div>';
    //
    //
    // $('.modulePreContent.module_BulkImportFile').append(available_maps_html);


    $('#flup').change(function (event) {

        event.preventDefault();

        var files = event.target.files;
        var path = files[0].webkitRelativePath;
        var Folder = path.split("/");

        // console.log(files);
        // console.log(path);
        // console.log(Folder);

        importform = $('#importform');

        // var form_data = new FormData(importform);
        // form_data.append('file', files);

        //var formData = new FormData($(this).parents('form')[0]);

        url = '/admin/bulkimportfile/getfiles';

        var form_data = new FormData();
        var ins = document.getElementById('multiFiles').files.length;
        for (var x = 0; x < ins; x++) {
            form_data.append("files[]", document.getElementById('multiFiles').files[x]);
        }

        // console.log(form_data);

        $.ajax({
            url: url, // point to server-side PHP script
            dataType: 'text', // what to expect back from the PHP script
            cache: false,
            contentType: false,
            processData: false,
            data: form_data,
            type: 'post',
            success: function (response) {
                $('#msg').html(response); // display success response from the PHP script
            },
            error: function (response) {
                $('#msg').html(response); // display error response from the PHP script
            }
        });

        var formData = new FormData(importform);

        // console.log(importform);

        //var filedata = document.getElementsByName("file");

        // len = files.length;
        // var i = 0;
        //
        // //console.log(len);
        //
        // for (; i < len; i++) {
        //     file = files[i];
        //
        //     // console.log(file);
        //
        //     formData.append('files[]', file);
        // }

        //formData.append('file', files);

        // console.log(formData);

        // url = '/admin/bulkimportfile/getfiles';
        //
        // $.ajax({
        //     url: url,
        //     type: 'POST',
        //     xhr: function() {
        //         var myXhr = $.ajaxSettings.xhr();
        //         return myXhr;
        //     },
        //     success: function (data) {
        //         //alert("Data Uploaded: "+data);
        //     },
        //     data: formData,
        //     cache: false,
        //     contentType: false,
        //     processData: false
        // });
        // return false;


        // $.each(files , function() {
        //
        // });
        //
        // $('.selected-files-source').append('<input type="file" name="source">');

        // $.ajax({
        //     url: url,
        //     type: 'POST',
        //     data: form_data,
        //     dataType: 'text',
        //     cache: false,
        //     contentType: false,
        //     processData: false,
        // }).done(function (data) {
        //
        //     //$('.selected-files').html(data);
        //     // console.log(data);
        // }).fail(function (err) {
        //     console.log(err);
        // });


    })


    $('#multiFiles').change(function (event) {
        // console.log('change');

        $('#upload').click();
    });


    $('#upload').on('click', function () {
        url = '/admin/bulkimportfile/getfiles';

        var form_data = new FormData();
        var ins = document.getElementById('multiFiles').files.length;
        for (var x = 0; x < ins; x++) {
            form_data.append("files[]", document.getElementById('multiFiles').files[x]);
        }
        $.ajax({
            url: url,
            dataType: 'html',
            cache: false,
            contentType: false,
            processData: false,
            data: form_data,
            type: 'post',
            success: function (response) {
                $('.files-map-block').html(response);

                $('#table-selected-files .o-icon-more.sidebar-content').click(function () {

                    $(this).parent().parent().find('.full_info').toggle();
                })

                add_button_action();

            },
            error: function (response) {
                //$('#msg').html(response); // display error response from the PHP script
            }
        });
    });

    function add_button_action() {
        listterms = $('.listterms').html();

        $('.omeka_property .js-add-action').unbind('click');

        $('.omeka_property .js-add-action').on('click', function () {


            var row_td = $(this).parent().parent().parent();
            row_td.find('.omeka_list_property').append(listterms);

            count = parseInt(row_td.parent().attr('attr-property-count'));

            count++;
            row_td.parent().attr('attr-property-count', count);

            add_button_action();

            if (!row_td.hasClass('js-prepare_to_save')) {
                row_td.addClass('js-prepare_to_save');
                // row_td.parent().find('.js-save-button').prepend('<button type="submit" name="add-item-submit">Save</button>');
                row_td.parent().addClass('listterms_with_action_row');
            }

            $('.omeka_property .js-single-remove-action').unbind('click');


            $('.omeka_list_property .js-single-remove-action').on('click', function () {

                listterms_with_action_row = $(this).parent().parent().parent();

                count = parseInt(listterms_with_action_row.parent().parent().attr('attr-property-count'));

                count--;

                listterms_with_action_row.parent().parent().attr('attr-property-count', count);

                if (count == 0) {
                    listterms_with_action_row.parent().parent().removeClass('listterms_with_action_row').find('.js-save-button').html('');
                    listterms_with_action_row.parent().parent().find('.js-prepare_to_save').removeClass('js-prepare_to_save');
                }

                $(this).parent().parent().remove();


            });

        });

        $('.omeka_property .js-remove-action').on('click', function () {
            $(this).parent().parent().remove();
        });

        $('.full_info').find('.js-save-button button').click(function () {
            save_action($(this));
        })
    }

    function save_action(row) {

        omeka_item_id = row.parents('.selected-files-row').find('.omeka_item_id').val();

        listterms_select_total = [];

        /**
         * First find new added fields
         */
        row.parents('tr.selected-files-row').find('.listterms_with_action_row').each(function () {

            file_field_property = $(this).find('.js-file_field_property').html();
            listterms_select = [];
            $(this).find('.listterms_with_action').each(function () {

                listterms_select.push($(this).find('.listterms_select').val());

            });

            if (listterms_select.length > 0)
                listterms_select_total.push({
                    'field': file_field_property,
                    'property': listterms_select
                });

        });


        /**
         * Add existed fields
         */

        row.parents('tr.selected-files-row').find('.with_property').each(function () {

            file_field_property = $(this).find('.js-file_field_property').html();
            listterms_select = [];
            $(this).find('.omeka_property_name').each(function () {

                listterms_select.push($(this).html());

            });

            if (listterms_select.length > 0)
                listterms_select_total.push({
                    'field': file_field_property,
                    'property': listterms_select
                });

        });

        /**
         * Check double omeka property fields
         */


        check_same_property = '';

        $('.response').removeClass('error');

        property_for_check = [];

        $.each(listterms_select_total, function (key, val) {

            $.each(val['property'], function (pr_key, pr_val) {

                check_same_property = $.inArray(pr_val, property_for_check);

                if (check_same_property == 0) {
                    $('.response').addClass('error');
                    $('.response').html("Omeka property can't be same !");
                    return false;
                } else {
                    property_for_check.push(pr_val);
                }
            });

            if (check_same_property == 0) {
                return false;
            }
        });


        if (check_same_property == 0) {
            $("html, body").animate({scrollTop: 0}, "slow");
            return false;
        }

        url = '/admin/bulkimportfile/saveoption';


        var form_data = {
            "omeka_item_id": omeka_item_id,
            "file_field_property": file_field_property,
            "listterms_select": listterms_select_total
        }

        $.ajax({
            url: url,
            data: form_data,
            type: 'post',
            success: function (response) {
                $('.response').addClass('success');
                $('.response').html(response);
                $("html, body").animate({scrollTop: 0}, "slow");
            },
            error: function (response) {
                $('.response').html(response);
            }
        });


    }

    directory = '';

    $('.make_import_form .check_button').click(function () {

        directory = {'folder' : $('.make_import_form #directory').val()};

        url = '/admin/bulkimportfile/checkfolder';

        $.ajax({
            url: url,
            data: directory,
            type: 'post',
            beforeSend: function() {
                $('.modal-loader').show();
            },
            success: function (response) {
                $('.response').html(response);
            },
            error: function (response) {
                $('.response').html(response);
            },
            complete: function () {
                $('.modal-loader').hide();
                action_for_recognize_files();
            }
        });

        // console.log(directory);

        return false;
    });

    make_action = false;

    data_for_recognize  = {};

    create_action = '';

    file_position_upload = 0;

    total_files_for_upload = 0;

    function make_single_file_upload(file_position_upload) {


        // console.log(make_action);
        //
        // console.log(total_files_for_upload);
        //
        // console.log(data_for_recognize['filenames'][file_position_upload]);

        url = '/admin/bulkimportfile/actionmakeimport';

        directory = $('.make_import_form #directory').val();

        $('.directory').val(directory);

        if ((file_position_upload >= total_files_for_upload) || (typeof data_for_recognize['filenames'][file_position_upload] == 'undefined')) {
            clearTimeout(create_action);
            $('.response').append('Import done');
            $('.response').find('.total_info').remove();
        } else {

            if (make_action == true) {


                data_for_recognize_single = {
                    'data_for_recognize_single' : data_for_recognize['filenames'][file_position_upload],
                    'directory': directory,
                    'delete-file': $('#delete-file').val(),
                    'data_for_recognize_row_id' : data_for_recognize['row_id'][file_position_upload]
                };

                $.ajax({
                    url: url,
                    data: data_for_recognize_single,
                    type: 'post',
                    beforeSend: function() {
                        make_action = false;
                        clearTimeout(create_action);
                    },
                    success: function (response) {

                        // console.log(response.length);

                        /**
                         *
                         * if error , field not recognize
                         *
                         */
                        // if (response.length > 1)
                        // {
                        //     $('.response .isset_yes.row_id_'+row).addClass('make_error');
                        //     $('.response .isset_yes.row_id_'+row).find('.status').html('NO');
                        //
                        // } else {
                        //     $('.response .isset_yes.row_id_'+row).addClass('make_success');
                        //     $('.response .isset_yes.row_id_'+row).find('.status').html('OK');
                        // }

                        row = parseInt(response);

                        $('.response .isset_yes.row_id_'+row).addClass('make_success');
                        $('.response .isset_yes.row_id_'+row).find('.status').html('OK');


                    },
                    error: function (response) {
                        $('.response').html(response);
                    },
                    complete: function (response) {
                        make_action = true;
                        file_position_upload++;
                        create_action = setTimeout(make_single_file_upload(file_position_upload), 1000);
                    }
                });


            } else {
                clearTimeout(create_action);
            }

        }





    }

    function action_for_recognize_files() {
        $('.js-recognize_files').click(function () {

            filenames = [];
            row_id = [];


            $('.response').find('.total_info').remove();

            $('.response .isset_yes').each(function () {
                filenames.push($(this).find('.filename').html());
                row_id.push($(this).find('.filename').attr('attr-row-id'));
            });

            data_for_recognize = {
                'directory': directory,
                'filenames': filenames,
                'row_id': row_id
            }

            // console.log(data_for_recognize);

            total_files_for_upload = data_for_recognize['filenames'].length;

            make_action = true;

            create_action = setTimeout(make_single_file_upload(file_position_upload), 1000);


        });

    }

    $('#delete-file').click(function () {
        if_checked = $(this).val();

        if (if_checked == 'no') {
            $(this).val('yes');
        } else {
            $(this).val('no')
        }
    })

});