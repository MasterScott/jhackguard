<?php
/**
 * @version     2.0.0
 * @package     com_jhackguard
 * @copyright   Copyright (C) 2013. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Valeri Markov <val@jhackguard.com> - http://www.jhackguard.com/
 */

// no direct access
defined('_JEXEC') or die;

JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');
JHtml::_('bootstrap.tooltip');
JHtml::_('behavior.multiselect');
JHtml::_('formbehavior.chosen', 'select');

// Import CSS
$document = JFactory::getDocument();


$user	= JFactory::getUser();
$userId	= $user->get('id');
$listOrder	= $this->state->get('list.ordering');
$listDirn	= $this->state->get('list.direction');
$canOrder	= $user->authorise('core.edit.state', 'com_jhackguard');
$saveOrder	= $listOrder == 'a.ordering';
if ($saveOrder)
{
	$saveOrderingUrl = 'index.php?option=com_jhackguard&task=ondemandscans.saveOrderAjax&tmpl=component';
	JHtml::_('sortablelist.sortable', 'ondemandscanList', 'adminForm', strtolower($listDirn), $saveOrderingUrl);
}

?>
<script type="text/javascript">
	Joomla.orderTable = function() {
		table = document.getElementById("sortTable");
		direction = document.getElementById("directionTable");
		order = table.options[table.selectedIndex].value;
		if (order != '<?php echo $listOrder; ?>') {
			dirn = 'asc';
		} else {
			dirn = direction.options[direction.selectedIndex].value;
		}
		Joomla.tableOrdering(order, dirn, '');
	}
    
    jQuery( document ).ready(function( $ ) {
       	//This is the id number generated by verify_integrity() function.
    	var id = 0;
        var startFrom = 0;
    	var filesCount, partialStop, percentagePerRun,currentPercentage = 0;
    	var maxFiles;
        var maxSize = 5242880;
        var indexStep = 3000;
        var maxInserts = 300;

    	$("#start-scan-btn").click(function(e){
            e.preventDefault();
            jstore_config();
            clear_console();
            verify_integrity();
        });

        function jstore_config()
        {
        	maxFiles = $("#jhackguard-max-scan-files").val();
            maxFiles = parseInt(maxFiles,10);
            maxSize = parseInt($("#jhackguard-max-scan-fsize").val(),10);
            indexStep = parseInt($("#jhackguard-max-scan-step").val(),10);
            maxInserts = parseInt($("#jhackguard-max-inserts").val(),10);
        }

        function clear_console()
        {
        	$("#md").html("");
        }

        function verify_integrity()
        {
        	$("#md").append("Verifying rules file integrity...");
        	$.post( "index.php?option=com_jhackguard&task=ondemandscans.verify_integrity&view=ondemandscans&format=raw", { cachePrevent: "0",
        		maxFiles: maxFiles})
                .done(function( data ) {
                    data = $.parseJSON(data);
                    if(data.success){
                        $("#md").append("<span style='color: green;'> Done</span>");
                        id = data.id;
                        build_files();
                    } else {
                        $("#md").append("<span style='color: red;'> Failed: </span>"+data.msg);
                    }
                })
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
                }); 
        }

        function build_files()
        {
        	if(partialStop > 0)
            {
                $("#files_rebuild_cont").html(partialStop + ' files');
            } else 
            {
                $("#md").append("<br/>Building files database...<span style='color: green;' id='files_rebuild_cont'></span>");
            }
        	$.post( "index.php?option=com_jhackguard&task=ondemandscans.build_files&view=ondemandscans&format=raw", { cachePrevent: "1",
        		maxFiles: maxFiles, id: id, partialStop: partialStop, indexStep : indexStep, maxInserts : maxInserts, maxSize : maxSize})
                .done(function( data ) {
                    data = $.parseJSON(data);
                    if(data.success){
                        $("#files_rebuild_cont").html(" Done");
                        //Calculate the percentage per run, based on maxFiles.
                        filesCount = data.count;
                        percentagePerRun = (maxFiles / filesCount) * 100;
                        console.log("Each run will increment the progress by "+percentagePerRun);
                        create_progressbars();
                    } else {
                        if(data.partialRun)
                        {
                            partialStop = data.partialStop;
                            build_files();
                        }
                    }
                })
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
                });
        }

        function create_progressbars()
        {
            $("#md").append('<br/><h3>Scanning Progress: </h3><br/><div class="progress"><div id="control-bar" class="bar bar-success" style="width: 0%;"></div><div id="control-bar-current" class="bar bar-warning active" style="width: 0%;"></div></div>');
            $("#control-bar").css({ "width": currentPercentage + '%'});
            $("#control-bar-current").css({ "width": percentagePerRun + '%'});
            scan_step();
        }

        function scan_step()
        {
            $.post( "index.php?option=com_jhackguard&task=ondemandscans.scan_files&view=ondemandscans&format=raw", { cachePrevent: "1",
                maxFiles: maxFiles, startFrom: startFrom, id: id})
                .done(function( data ) {
                    data = $.parseJSON(data);
                    if(data.success){
                        if(data.continue)
                        {
                            startFrom = startFrom + maxFiles.valueOf();
                            update_progressbars(data.continue);
                        } else 
                        {
                            finish_scan();
                        }
                    } else {
                        $("#md").append("<span style='color: red;'> Failed: </span>"+data.msg);
                    }
                })
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
                });
        }

        function update_progressbars(c)
        {
            currentPercentage = currentPercentage + percentagePerRun;
            if(currentPercentage > 100)
            {
                currentPercentage = 100;
            }
            $("#control-bar").css({ "width": currentPercentage + '%'});
            if(c){
                scan_step();
            } else {
                finish_scan();
            }
        }

        //Bind buttons to view_results();
        $(".results_view_btn").click(function(e){
            view_results($(this).data('scanid'));
        });

        $(".results_delete_btn").click(function(e){
            resultsid = $(this).data('scanid');
            $(this).parent().parent().hide();

            $.post( "index.php?option=com_jhackguard&task=ondemandscans.delete_results&view=ondemandscans&format=raw", { cachePrevent: "1",
                id: resultsid})
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
            });
        });

        function view_results(resultsid)
        {
            $("#md").html("Loading...");
            $.post( "index.php?option=com_jhackguard&task=ondemandscans.show_results&view=ondemandscans&format=raw", { cachePrevent: "1",
                id: resultsid})
                .done(function( data ) {
                    data = $.parseJSON(data);
                    if(data.success){ 
                       $("#md").html(data.html);
                    } else {
                        $("#md").append("<span style='color: red;'> Failed: </span>"+data.msg);
                    }
                })
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
            });
        }

        function finish_scan()
        {
            //We should always set this to 100, since the last request never updated the progressbar
            $("#control-bar").css({ "width" : "100%"});
            $.post( "index.php?option=com_jhackguard&task=ondemandscans.show_results&view=ondemandscans&format=raw", { cachePrevent: "1",
                id: id})
                .done(function( data ) {
                    data = $.parseJSON(data);
                    if(data.success){
                       $("#md").append(data.html);
                    } else {
                        $("#md").append("<span style='color: red;'> Failed: </span>"+data.msg);
                    }
                })
                .fail(function( data) {
                    $("#md").append("<span style='color: red;'> Failed</span>");
            });
        }

    });
    
</script>

<?php
//Joomla Component Creator code to allow adding non select list filters
if (!empty($this->extra_sidebar)) {
    $this->sidebar .= $this->extra_sidebar;
}
?>

<?php
if (version_compare(PHP_VERSION, '5.3.6') <= 0) {
    JFactory::getApplication()->enqueueMessage(
        JText::_('COM_JHACKGUARD_PHP_VERSION_SCAN'),'error');
}
?>

<?php if(!empty($this->sidebar)): ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>    
        <div id="previous_scans" class="span9">
            <p style="text-align: center;"><h1>Previous Scan Results</h1></p>
            <div>
                <table class="table table-striped" id="logList">
                <thead>
                    <tr>
                        <th width="1%" class="hidden-phone">
                            
                        </th>
                                        
                    <th class='left'>
                        <strong>Scan ID</strong>                
                    </th>

                    <th class='left'>
                        <strong>Scan Date</strong>                
                    </th>

                    <th class='left'>
                        <strong>Infected Items</strong>
                    </th>

                    <th>
                        <strong>Actions</strong>
                    </th>
                    </thead>
                    <tbody>
<?php
        $db = JFactory::getDbo();
        
        // Create a new query object.
        $query = $db->getQuery(true);
        $query->select($db->quoteName('scan_id') . ", COUNT(*) as 'infected'");
        $query->from($db->quoteName('#__jhackguard_scan_hits'));
        $query->group('scan_id');
        $db->setQuery($query);
        $list = $db->loadObjectList();
        
        if(count($list) > 0)
        {
            foreach($list as $scan)
            {
                echo("<tr><td></td><td>".$scan->scan_id."</td>");
                echo("<td>".date("F j, Y, g:i a",$scan->scan_id)."</td>");
                echo("<td>".$scan->infected."</td>");
                echo('<td><button data-scanid="'.$scan->scan_id.'" class="btn btn-mini results_view_btn">View</button> | <button data-scanid="'.$scan->scan_id.'" class="btn btn-mini btn-danger results_delete_btn">Delete</button></td>');
                echo("</tr>");
            }
        } else {
            echo("<tr><td colspan='5'><center>No previous reports with data found.</center></td></tr>");
        }

?>

                    </tbody>
                </table>
            </div>
        </div> <!-- Table Div End -->

        <div id="md" class="span9">
            <h1>Malicious Code Scanner</h1>
            
        <form class="form-horizontal">
            <div class="control-group">
                <div class="control-label">
                    <label id="jhackguard-max-scan-files-lbl" for="jhackguard-max-scan-files" >Max files scans per run:</label>                             
                </div>
                <div class="controls">
                    <input type="text" name="max-files" id="jhackguard-max-scan-files" value="500" size="5" />                      
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="jhackguard-max-scan-fsize-lbl" for="jhackguard-max-scan-fsize" >Max filesize to scan:</label>                             
                </div>
                <div class="controls">
                    <input type="text" name="max-files" id="jhackguard-max-scan-fsize" value="5242880" size="10" />                      
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="jhackguard-max-scan-step-lbl" for="jhackguard-max-scan-step" >Files indexing step:</label>                             
                </div>
                <div class="controls">
                    <input type="text" name="max-files" id="jhackguard-max-scan-step" value="3000" size="10" />                      
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="jhackguard-max-inserts-lbl" for="jhackguard-max-inserts" >Max INSERTS per query:</label>                             
                </div>
                <div class="controls">
                    <input type="text" name="max-files" id="jhackguard-max-inserts" value="300" size="10" />                      
                </div>
            </div>

            <div class="control-group">
                <button class="btn btn-danger" id="start-scan-btn">Start scan now</button>
            </div>
        </form>
        </div> <!-- MD end -->
    </div>
