<?php

error_reporting( error_reporting() & ~E_NOTICE );

$forDelete = true; 
$forUpload = true; 
$forCreateFolder = true; 
$allow_direct_link = true; 
$allow_show_folders = true; 

$disallowed_extensions = ['php'];  
$hidden_extensions = ['php']; 

// must be in UTF-8 or `basename` doesn't work
setlocale(LC_ALL,'en_US.UTF-8');

$tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
if(DIRECTORY_SEPARATOR==='\\') $tmp_dir = str_replace('/',DIRECTORY_SEPARATOR,$tmp_dir);
$tmp = get_absolute_path($tmp_dir . '/' .$_REQUEST['file']);

if($tmp === false)
    err(404,'File or Directory Not Found');
if(substr($tmp, 0,strlen($tmp_dir)) !== $tmp_dir)
    err(403,"Forbidden");
if(strpos($_REQUEST['file'], DIRECTORY_SEPARATOR) === 0)
    err(403,"Forbidden");


if(!$_COOKIE['_sfm_xsrf'])
    setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));
if($_POST) {
    if($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
        err(403,"XSRF Failure");
}

$file = $_REQUEST['file'] ?: '.';
if($_GET['do'] == 'list') {
    if (is_dir($file)) {
        $directory = $file;
        $result = [];
        $files = array_diff(scandir($directory), ['.','..']);
        foreach ($files as $entry) if (!is_entry_ignored($entry, $allow_show_folders, $hidden_extensions)) {
        $i = $directory . '/' . $entry;
        $stat = stat($i);
            $result[] = [
                'mtime' => $stat['mtime'],
                'size' => $stat['size'],
                'name' => basename($i),
                'path' => preg_replace('@^\./@', '', $i),
                'is_dir' => is_dir($i),
                'is_deleteable' => $forDelete && ((!is_dir($i) && is_writable($directory)) ||
                                                           (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i))),
                'is_readable' => is_readable($i),
                'is_writable' => is_writable($i),
                'is_executable' => is_executable($i),
            ];
        }
    } else {
        err(412,"Not a Directory");
    }
    echo json_encode(['success' => true, 'is_writable' => is_writable($file), 'results' =>$result]);
    exit;
} elseif ($_POST['do'] == 'delete') {
    if($forDelete) {
        rmrf($file);
    }
    exit;
} elseif ($_POST['do'] == 'mkdir' && $forCreateFolder) {
    $dir = $_POST['name'];
    $dir = str_replace('/', '', $dir);
    if(substr($dir, 0, 2) === '..')
        exit;
    chdir($file);
    @mkdir($_POST['name']);
    exit;
} elseif ($_POST['do'] == 'upload' && $forUpload) {
    foreach($disallowed_extensions as $ext)
        if(preg_match(sprintf('/\.%s$/',preg_quote($ext)), $_FILES['file_data']['name']))
            err(403,"Files of this type are not allowed.");

    $res = move_uploaded_file($_FILES['file_data']['tmp_name'], $file.'/'.$_FILES['file_data']['name']);
    exit;
} elseif ($_GET['do'] == 'download') {
    $filename = basename($file);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    header('Content-Type: ' . finfo_file($finfo, $file));
    header('Content-Length: '. filesize($file));
    header(sprintf('Content-Disposition: attachment; filename=%s',
        strpos('MSIE',$_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\"" ));
    ob_flush();
    readfile($file);
    exit;
}

function is_entry_ignored($entry, $allow_show_folders, $hidden_extensions) {
    if ($entry === basename(__FILE__)) {
        return true;
    }

    if (is_dir($entry) && !$allow_show_folders) {
        return true;
    }

    $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (in_array($ext, $hidden_extensions)) {
        return true;
    }

    return false;
}

function rmrf($dir) {
    if(is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.','..']);
        foreach ($files as $file)
            rmrf("$dir/$file");
        rmdir($dir);
    } else {
        unlink($dir);
    }
}
function is_recursively_deleteable($d) {
    $stack = [$d];
    while($dir = array_pop($stack)) {
        if(!is_readable($dir) || !is_writable($dir))
            return false;
        $files = array_diff(scandir($dir), ['.','..']);
        foreach($files as $file) if(is_dir($file)) {
            $stack[] = "$dir/$file";
        }
    }
    return true;
}

function get_absolute_path($path) {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }

function err($code,$msg) {
    http_response_code($code);
    echo json_encode(['error' => ['code'=>intval($code), 'msg' => $msg]]);
    exit;
}

function asBytes($ini_v) {
    $ini_v = trim($ini_v);
    $s = ['g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10];
    return intval($ini_v) * ($s[strtolower(substr($ini_v,-1))] ?: 1);
}
$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="deskripsi" content="File Manager PHP">
    <meta name="author" content="Ifta Marlienna">
    <title>File Manager</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="style.css" rel="stylesheet" type="text/css" media="screen">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>

<script>
(function($){
    $.fn.tablesorter = function() {
        var $table = this;
        this.find('th').click(function() {
            var idx = $(this).index();
            var direction = $(this).hasClass('sort_asc');
            $table.tablesortby(idx,direction);
        });
        return this;
    };
    $.fn.tablesortby = function(idx,direction) {
        var $rows = this.find('tbody tr');
        function elementToVal(a) {
            var $a_elem = $(a).find('td:nth-child('+(idx+1)+')');
            var a_val = $a_elem.attr('data-sort') || $a_elem.text();
            return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
        }
        $rows.sort(function(a,b){
            var a_val = elementToVal(a), b_val = elementToVal(b);
            return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
        })
        this.find('th').removeClass('sort_asc sort_desc');
        $(this).find('thead th:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
        for(var i =0;i<$rows.length;i++)
            this.append($rows[i]);
        this.settablesortmarkers();
        return this;
    }
    $.fn.retablesort = function() {
        var $e = this.find('thead th.sort_asc, thead th.sort_desc');
        if($e.length)
            this.tablesortby($e.index(), $e.hasClass('sort_desc') );

        return this;
    }
    $.fn.settablesortmarkers = function() {
        this.find('thead th span.indicator').remove();
        this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
        this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
        return this;
    }
})(jQuery);

$(function(){
    var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)')||0)[2];
    var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;
    var $tbody = $('#list');
    $(window).on('hashchange',list).trigger('hashchange');
    $('#table').tablesorter();

    $('#table').on('click','.delete',function(data) {
        $.post("",{'do':'delete',file:$(this).attr('data-file'),xsrf:XSRF},function(response){
            list();
        },'json');
        return false;
    });

    $('#mkdir').submit(function(e) {
        var hashval = decodeURIComponent(window.location.hash.substr(1)),
            $dir = $(this).find('[name=name]');
        e.preventDefault();
        $dir.val().length && $.post('?',{'do':'mkdir',name:$dir.val(),xsrf:XSRF,file:hashval},function(data){
            list();
        },'json');
        $dir.val('');
        return false;
    });
<?php if($forUpload): ?>
    $('#drop').on('dragover',function(){
        $(this).addClass('drag_over');
        return false;
    }).on('dragend',function(){
        $(this).removeClass('drag_over');
        return false;
    }).on('drop',function(e){
        e.preventDefault();
        var files = e.originalEvent.dataTransfer.files;
        $.each(files,function(k,file) {
            uploadFile(file);
        });
        $(this).removeClass('drag_over');
    });
    $('input[type=file]').change(function(e) {
        e.preventDefault();
        $.each(this.files,function(k,file) {
            uploadFile(file);
        });
    });


    function uploadFile(file) {
        var folder = decodeURIComponent(window.location.hash.substr(1));

        if(file.size > MAX_UPLOAD_SIZE) {
            var $error_row = renderFileSizeErrorRow(file,folder);
            $('#uploadProgress').append($error_row);
            window.setTimeout(function(){$error_row.fadeOut();},5000);
            return false;
        }

        var $row = renderFileUploadRow(file,folder);
        $('#uploadProgress').append($row);
        var fd = new FormData();
        fd.append('file_data',file);
        fd.append('file',folder);
        fd.append('xsrf',XSRF);
        fd.append('do','upload');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '?');
        xhr.onload = function() {
            $row.remove();
            list();
        };
        xhr.upload.onprogress = function(e){
            if(e.lengthComputable) {
                $row.find('.progress').css('width',(e.loaded/e.total*100 | 0)+'%' );
            }
        };
        xhr.send(fd);
    }
    function renderFileUploadRow(file,folder) {
        return $row = $('<div/>')
            .append( $('<span class="fileuploadname" />').text( (folder ? folder+'/':'')+file.name))
            .append( $('<div class="progress_track"><div class="progress"></div></div>')  )
            .append( $('<span class="size" />').text(formatFileSize(file.size)) )
    };
    function renderFileSizeErrorRow(file,folder) {
        return $row = $('<div class="error" />')
            .append( $('<span class="fileuploadname" />').text( 'Error: ' + (folder ? folder+'/':'')+file.name))
            .append( $('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>'
                +' exceeds max upload size of <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>')  );
    }
<?php endif; ?>

    function list() {
        var hashval = window.location.hash.substr(1);
        $.get('?do=list&file='+ hashval,function(data) {
            $tbody.empty();
            $('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
            if(data.success) {
                $.each(data.results,function(k,v){
                    $tbody.append(renderFileRow(v));
                });
                !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td></tr>')
                data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
            } else {
                console.warn(data.error.msg);
            }
            $('#table').retablesort();
        },'json');
    }
    function renderFileRow(data) {
        var $link = $('<a class="name" />')
            .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './'+ encodeURIComponent(data.path))
            .text(data.name);
        var allow_direct_link = <?php echo $allow_direct_link?'true':'false'; ?>;
            if (!data.is_dir && !allow_direct_link)  $link.css('pointer-events','none');
        var $dl_link = $('<a/>').attr('href','?do=download&file='+ encodeURIComponent(data.path))
            .addClass('download').text('download');
        var $delete_link = $('<a href="#" />').attr('data-file',data.path).addClass('delete').text('delete');
        var perms = [];
        if(data.is_readable) perms.push('read');
        if(data.is_writable) perms.push('write');
        if(data.is_executable) perms.push('exec');
        var $html = $('<tr />')
            .addClass(data.is_dir ? 'is_dir' : '')
            .append( $('<td class="first" />').append($link) )
            .append( $('<td/>').attr('data-sort',data.is_dir ? -1 : data.size)
                .html($('<span class="size" />').text(formatFileSize(data.size))) )
            .append( $('<td/>').attr('data-sort',data.mtime).text(formatTimestamp(data.mtime)) )
            .append( $('<td/>').text(perms.join('+')) )
            .append( $('<td/>').append($dl_link).append( data.is_deleteable ? $delete_link : '') )
        return $html;
    }
    function renderBreadcrumbs(path) {
        var base = "",
            $html = $('<div/>').append( $('') );
        $.each(path.split('%2F'),function(k,v){
            if(v) {
                var v_as_text = decodeURIComponent(v);
                $html.append( $('<span/>').text(' ▸ ') )
                    .append( $('<a/>').attr('href','#'+base+v).text(v_as_text) );
                base += v + '%2F';
            }
        });
        return $html;
    }
    function formatTimestamp(unix_timestamp) {
        var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var d = new Date(unix_timestamp*1000);
        return [m[d.getMonth()],' ',d.getDate(),', ',d.getFullYear()," ",
            (d.getHours() % 12 || 12),":",(d.getMinutes() < 10 ? '0' : '')+d.getMinutes(),
            " ",d.getHours() >= 12 ? 'PM' : 'AM'].join('');
    }
    function formatFileSize(bytes) {
        var s = ['bytes', 'KB','MB','GB','TB','PB','EB'];
        for(var pos = 0;bytes >= 1000; pos++,bytes /= 1024);
        var d = Math.round(bytes*10);
        return pos ? [parseInt(d/10),".",d%10," ",s[pos]].join('') : bytes + ' bytes';
    }
})

</script>

</head>

<body>
        <h2>Tugas Pemrograman Web File Manager<br>Ifta Marlienna R<br>M0517024</h2>

    <div id="wrapper">

        <div id="content">
            <div id="uploadProgress"></div>
            <?php if($forCreateFolder): ?>
                <form action="?" method="post" id="mkdir">
                    <label for=dirname>Create New Folder</label>
                    <input id=dirname type=text name=name value="">
                    <input type="submit" value="create">
                </form>

                <?php endif; ?>

                    <table class="table">
                        <thead>
                            <tr>
                                <th><b>Name</b></th>
                                <th><b>Size</b></th>
                                <th><b>Modified</b></th>
                                <th><b>Permissions</b></th>
                                <th><b>Actions</b></th>
                            </tr>
                        </thead>
                        <tbody id="list"></tbody>

                    </table>
                    <br>
                    <br>
                    <?php if($forUpload): ?>

                        <div class="drop">
                            <label>Upload File Here</label>
                            Drag & Drop
                            <br><b>or</b>
                            <br>
                            <input type="file" multiple>
                        </div>
                        <?php endif; ?>
        </div>

        <div id="footer">
            <div id="icon">
                <a href="https://github.com/iftamrln"> <i class="fa fa-github"></i></a>
            </div>
            <p>
                Copyright © 2019 Developed by <b>Iftamrln</b>
                <br>
            </p>
        </div>
</body>

</html>
