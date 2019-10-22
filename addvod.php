<?php
include "functions.php";
if (!isset($_SESSION['user_id'])) { header("Location: ./login.php"); exit; }

if (isset($_POST["submit_stream"])) {
    if (isset($_POST["edit"])) {
        $rArray = getStream($_POST["edit"]);
        unset($rArray["id"]);
    } else {
        $rArray = Array("type" => 2, "added" => time(), "read_native" => 0, "stream_all" => 0, "direct_source" => 1, "redirect_stream" => 1, "gen_timestamps" => 1, "transcode_attributes" => Array(), "stream_display_name" => "", "stream_source" => Array(), "category_id" => 0, "stream_icon" => "", "movie_propeties" =>"", "target_container" => "", "notes" => "", "custom_sid" => "", "custom_ffmpeg" => "", "transcode_profile_id" => 0, "enable_transcode" => 0, "auto_restart" => "[]", "allow_record" => 0, "rtmp_output" => 0, "epg_id" => "NULL", "channel_id" => "", "epg_lang" => "NULL", "tv_archive_server_id" => 0, "tv_archive_duration" => 0, "delay_minutes" => 0, "external_push" => Array(), "probesize_ondemand" => 512000, "custom_map" => "");
    }
    if ((isset($_POST["days_to_restart"])) && (preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $_POST["time_to_restart"]))) {
        $rTimeArray = Array("days" => Array(), "at" => $_POST["time_to_restart"]);
        foreach ($_POST["days_to_restart"] as $rID => $rDay) {
            $rTimeArray["days"][] = $rDay;
        }
        $rArray["auto_restart"] = $rTimeArray;
    } else {
        $rArray["auto_restart"] = "";
    }
    $rOnDemandArray = Array();
    if (isset($_POST["on_demand"])) {
        foreach ($_POST["on_demand"] as $rID) {
            $rOnDemandArray[] = $rID;
        }
    }
    
    foreach($_POST as $rKey => $rValue) {
        if (isset($rArray[$rKey])) {
            $rArray[$rKey] = $rValue;
        }
    }
    $rImportStreams = Array();
    if ((isset($_FILES["m3u_file"])) OR (isset($_POST["m3u_url"]))) {
        $rFile = '';
        if (!empty($_POST['m3u_url'])) {
            $rFile = file_get_contents($_POST['m3u_url']);
        } else if ((!empty($_FILES['m3u_file']['tmp_name'])) && (strtolower(pathinfo($_FILES['m3u_file']['name'], PATHINFO_EXTENSION)) == "m3u")) {
            $rFile = file_get_contents($_FILES['m3u_file']['tmp_name']);
        }
        preg_match_all('/(?P<tag>#EXTINF:-1)|(?:(?P<prop_key>[-a-z]+)=\"(?P<prop_val>[^"]+)")|(?<name>,[^\r\n]+)|(?<url>http[^\s]+)/', $rFile, $rMatches);
        $rResults = [];
        $rIndex = -1;
        for ($i = 0; $i < count($rMatches[0]); $i++) {
            $rItem = $rMatches[0][$i];
            if (!empty($rMatches['tag'][$i])) {
                ++$rIndex;
            } elseif (!empty($rMatches['prop_key'][$i])) {
                $rResults[$rIndex][$rMatches['prop_key'][$i]] = trim($rMatches['prop_val'][$i]);
            } elseif (!empty($rMatches['name'][$i])) {
                $rResults[$rIndex]['name'] = trim(substr($rItem, 1));
            } elseif (!empty($rMatches['url'][$i])) {
                $rResults[$rIndex]['url'] = trim($rItem);
            }
        }
        foreach ($rResults as $rResult) {
            $rImportArray = Array("stream_source" => Array($rResult["url"]), "stream_icon" => $rResult["tvg-logo"] ?: "", "stream_display_name" => $rResult["name"] ?: "", "epg_id" => 0, "epg_lang" => "NULL", "channel_id" => "");
            $rEPG = findEPG($rResult["tvg-id"]);
            if (isset($rEPG)) {
                $rImportArray["epg_id"] = $rEPG["epg_id"];
                $rImportArray["channel_id"] = $rEPG["channel_id"];
                if (!empty($rEPG["epg_lang"])) {
                    $rImportArray["epg_lang"] = $rEPG["epg_lang"];
                }
            }
            $rImportStreams[] = $rImportArray;
        }
    } else {
        $rImportArray = Array("stream_source" => Array(), "stream_icon" => $rArray["stream_icon"], "stream_display_name" => $rArray["stream_display_name"], "epg_id" => $rArray["epg_id"], "epg_lang" => $rArray["epg_lang"], "channel_id" => $rArray["channel_id"]);
        if (isset($_POST["stream_source"])) {
            foreach ($_POST["stream_source"] as $rID => $rURL) {
                if (strlen($rURL) > 0) {
                    $rImportArray["stream_source"][] = $rURL;
                }
            }
        }
        $rImportStreams[] = $rImportArray;
    }
    if (count($rImportStreams) > 0) {
        foreach ($rImportStreams as $rImportStream) {
            $rImportArray = $rArray;
            foreach (array_keys($rImportStream) as $rKey) {
                $rImportArray[$rKey] = $rImportStream[$rKey];
            }
            $rCols = "`".implode('`,`', array_keys($rImportArray))."`";
            $rValues = null;
            foreach (array_values($rImportArray) as $rValue) {
                isset($rValues) ? $rValues .= ',' : $rValues = '';
                if (is_array($rValue)) {
                    $rValue = json_encode($rValue);
                }
                if (is_null($rValue)) {
                    $rValues .= 'NULL';
                } else {
                    $rValues .= '\''.$db->real_escape_string($rValue).'\'';
                }
            }
            if (isset($_POST["edit"])) {
                $rCols = "`id`,".$rCols;
                $rValues = $_POST["edit"].",".$rValues;
            }
            $rQuery = "REPLACE INTO `streams`(".$rCols.") VALUES(".$rValues.");";
            if ($db->query($rQuery)) {
                if (isset($_POST["edit"])) {
                    $rInsertID = intval($_POST["edit"]);
                } else {
                    $rInsertID = $db->insert_id;
                }
            }
            if (isset($rInsertID)) {
                $rStreamExists = Array();
                if (isset($_POST["edit"])) {
                    $result = $db->query("SELECT `server_stream_id`, `server_id` FROM `streams_sys` WHERE `stream_id` = ".intval($rInsertID).";");
                    if (($result) && ($result->num_rows > 0)) {
                        while ($row = $result->fetch_assoc()) {
                            $rStreamExists[intval($row["server_id"])] = intval($row["server_stream_id"]);
                        }
                    }
                }
                if (isset($_POST["server_tree_data"])) {
                    $rStreamsAdded = Array();
                    $rServerTree = json_decode($_POST["server_tree_data"], True);
                    foreach ($rServerTree as $rServer) {
                        if ($rServer["parent"] <> "#") {
                            $rServerID = intval($rServer["id"]);
                            $rStreamsAdded[] = $rServerID;
                            if ($rServer["parent"] == "source") {
                                $rParent = "NULL";
                            } else {
                                $rParent = intval($rServer["parent"]);
                            }
                            if (in_array($rServerID, $rOnDemandArray)) {
                                $rOD = 1;
                            } else {
                                $rOD = 0;
                            }
                            
                            if (isset($rStreamExists[$rServerID])) {
                                $db->query("UPDATE `streams_sys` SET `parent_id` = ".$rParent.", `on_demand` = ".$rOD." WHERE `server_stream_id` = ".$rStreamExists[$rServerID].";");
                            } else {
                                $db->query("INSERT INTO `streams_sys`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES(".intval($rInsertID).", ".$rServerID.", ".$rParent.", ".$rOD.");");
                            }
                        }
                    }
                    foreach ($rStreamExists as $rServerID => $rDBID) {
                        if (!in_array($rServerID, $rStreamsAdded)) {
                            $db->query("DELETE FROM `streams_sys` WHERE `server_stream_id` = ".$rDBID.";");
                        }
                    }
                }
                $db->query("DELETE FROM `streams_options` WHERE `stream_id` = ".intval($rInsertID).";");
                if ((isset($_POST["user_agent"])) && (strlen($_POST["user_agent"]) > 0)) {
                    $db->query("INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(".intval($rInsertID).", 1, '".$db->real_escape_string($_POST["user_agent"])."');");
                }
                if ((isset($_POST["http_proxy"])) && (strlen($_POST["http_proxy"]) > 0)) {
                    $db->query("INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(".intval($rInsertID).", 2, '".$db->real_escape_string($_POST["http_proxy"])."');");
                }
                $_STATUS = 0;
            } else {
                $_STATUS = 1;
            }
        }
        if ((isset($_FILES["m3u_file"])) OR (isset($_POST["m3u_url"]))) {
            header("Location: ./streams.php");exit;
        } else if (!isset($_GET["id"])) {
            $_GET["id"] = $rInsertID;
        }
    } else {
        $_STATUS = 1;
    }
}

$rEPGSources = getEPGSources();
$rStreamArguments = getStreamArguments();
$rTranscodeProfiles = getTranscodeProfiles();

$rEPGJS = Array(0 => Array());
foreach ($rEPGSources as $rEPG) {
    $rEPGJS[$rEPG["id"]] = json_decode($rEPG["data"], True);
}

$rServerTree = Array();
$rOnDemand = Array();
$rServerTree[] = Array("id" => "source", "parent" => "#", "text" => "<strong>Stream Source</strong>", "icon" => "mdi mdi-youtube-tv", "state" => Array("opened" => true));
if (isset($_GET["id"])) {
    if (isset($_GET["import"])) { exit; }
    $rStream = getStream($_GET["id"]);
    if (!$rStream) {
        exit;
    }
    $rStreamOptions = getStreamOptions($_GET["id"]);
    $rStreamSys = getStreamSys($_GET["id"]);
    foreach ($rServers as $rServer) {
        if (isset($rStreamSys[intval($rServer["id"])])) {
            if ($rStreamSys[intval($rServer["id"])]["parent_id"] <> 0) {
                $rParent = intval($rStreamSys[intval($rServer["id"])]["parent_id"]);
            } else {
                $rParent = "source";
            }
        } else {
            $rParent = "#";
        }
        $rServerTree[] = Array("id" => $rServer["id"], "parent" => $rParent, "text" => $rServer["server_name"], "icon" => "mdi mdi-server-network", "state" => Array("opened" => true));
    }
    foreach ($rStreamSys as $rStreamItem) {
        if ($rStreamItem["on_demand"] == 1) {
            $rOnDemand[] = $rStreamItem["server_id"];
        }
    }
} else {
    foreach ($rServers as $rServer) {
        $rServerTree[] = Array("id" => $rServer["id"], "parent" => "#", "text" => $rServer["server_name"], "icon" => "mdi mdi-server-network", "state" => Array("opened" => true));
    }
}
include "header.php"; ?>
        <div class="wrapper boxed-layout">
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <a href="./streams.php<?php if (isset($_GET["category"])) { echo "?category=".$_GET["category"]; } ?>"><li class="breadcrumb-item"><i class="mdi mdi-backspace"></i> Back to Streams</li></a>
                                </ol>
                            </div>
                            <h4 class="page-title"><?php if (isset($rStream)) { echo $rStream["stream_display_name"]; } else if (isset($_GET["import"])) { echo "Import Streams"; } else { echo "Add Stream"; } ?></h4>
                        </div>
                    </div>
                </div>     
                <!-- end page title --> 
                <div class="row">
                    <div class="col-xl-12">
                        <?php if ((isset($_STATUS)) && ($_STATUS == 0)) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            Stream operation was completed successfully.
                        </div>
                        <?php } else if ((isset($_STATUS)) && ($_STATUS > 0)) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            There was an error performing this operation! Please check the form entry and try again.
                        </div>
                        <?php }
                        if (isset($rStream)) { ?>
                        <div class="card text-xs-center">
                            <div class="table">
                                <table id="datatable" class="table table-borderless mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th>Server</th>
                                            <th>Clients</th>
                                            <th>Uptime</th>
                                            <th>Actions</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" class="text-center">Loading stream information...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="card">
                            <div class="card-body">
                                <form<?php if(isset($_GET["import"])) { echo " enctype=\"multipart/form-data\""; } ?> action="./addvod.php<?php if (isset($_GET["import"])) { echo "?import"; } else if (isset($_GET["id"])) { echo "?id=".$_GET["id"]; } ?>" method="POST" id="stream_form">
                                    <?php if (isset($rStream)) { ?>
                                    <input type="hidden" name="edit" value="<?=$rStream["id"]?>" />
                                    <?php } ?>
                                    <input type="hidden" name="server_tree_data" id="server_tree_data" value="" />
                                    <div id="basicwizard">
                                        <ul class="nav nav-pills bg-light nav-justified form-wizard-header mb-4">
                                            <li class="nav-item">
                                                <a href="#stream-details" data-toggle="tab" class="nav-link rounded-0 pt-2 pb-2"> 
                                                    <i class="mdi mdi-account-card-details-outline mr-1"></i>
                                                    <span class="d-none d-sm-inline">Details</span>
                                                </a>
                                            </li>
                                            </li>
                                                                    
                                         </ul>
                                        <div class="tab-content b-0 mb-0 pt-0">
                                            <div class="tab-pane" id="stream-details">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <?php if (!isset($_GET["import"])) { ?>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="stream_display_name">Stream Name</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="stream_display_name" name="stream_display_name" value="<?php if (isset($rStream)) { echo $rStream["stream_display_name"]; } ?>">
                                                            </div>
                                                        </div>
                                                        <span class="streams">
                                                            <?php
                                                            if (isset($rStream)) {
                                                                $rStreamSources = json_decode($rStream["stream_source"], True);
                                                            } else {
                                                                $rStreamSources = Array("");
                                                            }
                                                            $i = 0;
                                                            foreach ($rStreamSources as $rStreamSource) { $i++
                                                            ?>
                                                            <div class="form-group row mb-4 stream-url">
                                                                <label class="col-md-4 col-form-label" for="stream_source"> Stream URL</label>
                                                                <div class="col-md-8 input-group">
                                                                    <input type="text" id="stream_source" name="stream_source[]" class="form-control" value="<?=$rStreamSource?>">
                                                                    <div class="input-group-append">
                                                                        <button class="btn btn-info waves-effect waves-light" onClick="moveUp(this);" type="button"><i class="mdi mdi-chevron-up"></i></button>
                                                                        <button class="btn btn-info waves-effect waves-light" onClick="moveDown(this);" type="button"><i class="mdi mdi-chevron-down"></i></button>
                                                                        <button class="btn btn-primary waves-effect waves-light" onClick="addStream();" type="button"><i class="mdi mdi-plus"></i></button>
                                                                        <button class="btn btn-danger waves-effect waves-light" onClick="removeStream(this);" type="button"><i class="mdi mdi-close"></i></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php } ?>
                                                        </span>
                                                        <?php } else { ?>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="m3u_url">M3U URL</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="m3u_url" name="m3u_url" value="">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="m3u_file">M3U File</label>
                                                            <div class="col-md-8">
                                                                <input type="file" id="m3u_file" name="m3u_file" />
                                                            </div>
                                                        </div>
                                                        <?php } ?>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="category_id">Category Name</label>
                                                            <div class="col-md-8">
                                                                <select name="category_id" id="category_id" class="form-control" data-toggle="select2">
                                                                    <?php foreach (getCategories("movie") as $rCategory) { ?>
                                                                    <option <?php if (isset($rStream)) { if (intval($rStream["category_id"]) == intval($rCategory["id"])) { echo "selected "; } } else if ((isset($_GET["category"])) && ($_GET["category"] == $rCategory["id"])) { echo "selected "; } ?>value="<?=$rCategory["id"]?>"><?=$rCategory["category_name"]?></option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <?php if (!isset($_GET["import"])) { ?>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="stream_icon">Stream Logo URL</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="stream_icon" name="stream_icon" value="<?php if (isset($rStream)) { echo $rStream["stream_icon"]; } ?>">
                                                            </div>
                                                        </div>
                                                        <?php } ?>
														<div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="movie_propeties">Movie propriet</label>
                                                            <div class="col-md-8">
                                                                <input type="text" value ='{"movie_image":null,"plot":null,"releasedate":null,"rating":null}' class="form-control" id="movie_propeties" name="movie_propeties" value="<?php if (isset($rStream)) { echo $rStream["movie_propeties"]; } ?>">
                                                            </div>
                                                        </div>
														<div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="target_container">Format Movie:</label>
                                                            <div class="col-md-8">
                                                                <input type="text" value ='["mp4"]' class="form-control" id="target_container" name="target_container" value="<?php if (isset($rStream)) { echo $rStream["target_container"]; } ?>">
                                                           </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="notes">Notes</label>
                                                            <div class="col-md-8">
                                                                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder=""><?php if (isset($rStream)) { echo $rStream["notes"]; } ?></textarea>
                                                            </div>
                                                        </div>
														
														  <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="transcode_profile_id">Transcoding Profile <i data-toggle="tooltip" data-placement="top" title="" data-original-title="Sometimes, in order to make a stream compatible with most devices, it must be transcoded. Please note that the transcode will only be applied to the server(s) that take the stream directly from the source, all other servers attached to the transcoding server will not transcode the stream." class="mdi mdi-information"></i></label>
                                                            <div class="col-md-8">
                                                                <select name="transcode_profile_id" id="transcode_profile_id" class="form-control" data-toggle="select2">
                                                                    <option <?php if (isset($rStream)) { if (intval($rStream["transcode_profile_id"]) == 0) { echo "selected "; } } ?>value="0">Transcoding Disabled</option>
                                                                    <?php foreach ($rTranscodeProfiles as $rProfile) { ?>
                                                                    <option <?php if (isset($rStream)) { if (intval($rStream["transcode_profile_id"]) == intval($rProfile["profile_id"])) { echo "selected "; } } ?>value="<?=$rProfile["profile_id"]?>"><?=$rProfile["profile_name"]?></option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
														<div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="servers">Server Tree</label>
                                                            <div class="col-md-8">
                                                                <div id="server_tree"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end col -->
                                                </div> <!-- end row -->
                                                <ul class="list-inline wizard mb-0">
                                                     <li class="next list-inline-item float-right">
                                                        <input name="submit_stream" type="submit" class="btn btn-primary" value="<?php if (isset($rStream)) { echo "Edit"; } else { echo "Add"; } ?>" />
                                                    </li>
                                                </ul>
                                            </div>

                                        </div> <!-- tab-content -->
                                    </div> <!-- end #basicwizard-->
                                </form>

                            </div> <!-- end card-body -->
                        </div> <!-- end card-->
                    </div> <!-- end col -->
                </div>
            </div> <!-- end container -->
        </div>
        <!-- end wrapper -->

        <!-- file preview template -->
        <div class="d-none" id="uploadPreviewTemplate">
            <div class="card mt-1 mb-0 shadow-none border">
                <div class="p-2">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <img data-dz-thumbnail class="avatar-sm rounded bg-light" alt="">
                        </div>
                        <div class="col pl-0">
                            <a href="javascript:void(0);" class="text-muted font-weight-bold" data-dz-name></a>
                            <p class="mb-0" data-dz-size></p>
                        </div>
                        <div class="col-auto">
                            <!-- Button -->
                            <a href="" class="btn btn-link btn-lg text-muted" data-dz-remove>
                                <i class="mdi mdi-close-circle"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Start -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 copyright text-center"><?=getFooter()?></div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->

        <!-- Vendor js -->
        <script src="assets/js/vendor.min.js"></script>
        <script src="assets/libs/jquery-toast/jquery.toast.min.js"></script>
        <script src="assets/libs/jquery-nice-select/jquery.nice-select.min.js"></script>
        <script src="assets/libs/switchery/switchery.min.js"></script>
        <script src="assets/libs/select2/select2.min.js"></script>
        <script src="assets/libs/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
        <script src="assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js"></script>
        <script src="assets/libs/clockpicker/bootstrap-clockpicker.min.js"></script>
        <script src="assets/libs/datatables/jquery.dataTables.min.js"></script>
        <script src="assets/libs/datatables/dataTables.bootstrap4.js"></script>
        <script src="assets/libs/datatables/dataTables.responsive.min.js"></script>
        <script src="assets/libs/datatables/responsive.bootstrap4.min.js"></script>
        <script src="assets/libs/datatables/dataTables.buttons.min.js"></script>
        <script src="assets/libs/datatables/buttons.bootstrap4.min.js"></script>
        <script src="assets/libs/datatables/buttons.html5.min.js"></script>
        <script src="assets/libs/datatables/buttons.flash.min.js"></script>
        <script src="assets/libs/datatables/buttons.print.min.js"></script>
        <script src="assets/libs/datatables/dataTables.keyTable.min.js"></script>
        <script src="assets/libs/datatables/dataTables.select.min.js"></script>

        <!-- Plugins js-->
        <script src="assets/libs/twitter-bootstrap-wizard/jquery.bootstrap.wizard.min.js"></script>

        <!-- Tree view js -->
        <script src="assets/libs/treeview/jstree.min.js"></script>
        <script src="assets/js/pages/treeview.init.js"></script>
        <script src="assets/js/pages/form-wizard.init.js"></script>

        <!-- App js-->
        <script src="assets/js/app.min.js"></script>
        
        <script>
        var rEPG = <?=json_encode($rEPGJS)?>;
        
        (function($) {
          $.fn.inputFilter = function(inputFilter) {
            return this.on("input keydown keyup mousedown mouseup select contextmenu drop", function() {
              if (inputFilter(this.value)) {
                this.oldValue = this.value;
                this.oldSelectionStart = this.selectionStart;
                this.oldSelectionEnd = this.selectionEnd;
              } else if (this.hasOwnProperty("oldValue")) {
                this.value = this.oldValue;
                this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
              }
            });
          };
        }(jQuery));
        
        function moveUp(elem) {
            if ($(elem).parent().parent().parent().prevAll().length > 0) {
                $(elem).parent().parent().parent().insertBefore($('.streams>div').eq($(elem).parent().parent().parent().prevAll().length-1));
            }
        }
        function moveDown(elem) {
            if ($(elem).parent().parent().parent().prevAll().length < $(".streams>div").length) {
                $(elem).parent().parent().parent().insertAfter($('.streams>div').eq($(elem).parent().parent().parent().prevAll().length+1));
            }
        }
        function addStream() {
            $(".stream-url:first").clone().appendTo(".streams");
            $(".stream-url:last label").html("Stream URL");
            $(".stream-url:last input").val("");
        }
        function removeStream(rField) {
            if ($('.stream-url').length > 1) {
                $(rField).parent().parent().parent().remove();
            } else {
                $(rField).parent().parent().find("#stream_source").val("");
            }
        }
        function selectEPGSource() {
            $("#channel_id").empty();
            $("#epg_lang").empty();
            if (rEPG[$("#epg_id").val()]) {
                $.each(rEPG[$("#epg_id").val()], function(key, data) {
                    $("#channel_id").append(new Option(data["display_name"], key, false, false));
                });
                selectEPGID();
            }
        }
        function selectEPGID() {
            $("#epg_lang").empty();
            if (rEPG[$("#epg_id").val()][$("#channel_id").val()]) {
                $.each(rEPG[$("#epg_id").val()][$("#channel_id").val()]["langs"], function(i, data) {
                    $("#epg_lang").append(new Option(data, data, false, false));
                });
            }
        }
        function reloadStream() {
            $("#datatable").DataTable().ajax.reload( null, false );
            setTimeout(reloadStream, 5000);
        }
        function api(rID, rServerID, rType) {
            if (rType == "delete") {
                if (confirm('Are you sure you want to delete this stream?') == false) {
                    return;
                }
            }
            $.getJSON("./api.php?action=stream&sub=" + rType + "&stream_id=" + rID + "&server_id=" + rServerID, function(data) {
                if (data.result == true) {
                    if (rType == "start") {
                        $.toast("Stream successfully started. It will take a minute or so before the stream becomes available.");
                    } else if (rType == "stop") {
                        $.toast("Stream successfully stopped.");
                    } else if (rType == "restart") {
                        $.toast("Stream successfully restarted. It will take a minute or so before the stream becomes available.");
                    } else if (rType == "delete") {
                        $("#stream-" + rID + "-" + rServerID).remove();
                        $.toast("Stream successfully deleted.");
                    }
                    $("#datatable").DataTable().ajax.reload( null, false );
                } else {
                    $.toast("An error occured while processing your request.");
                }
            }).fail(function() {
                $.toast("An error occured while processing your request.");
            });
        }
        $(document).ready(function() {
            $('select').select2({width: '100%'})
            var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
            elems.forEach(function(html) {
              var switchery = new Switchery(html);
            });
            $("#epg_id").on("select2:select", function(e) { 
                selectEPGSource();
            });
            $("#channel_id").on("select2:select", function(e) { 
                selectEPGID();
            });
            
            $(".clockpicker").clockpicker();
            
            $('#server_tree').jstree({ 'core' : {
                'check_callback': function (op, node, parent, position, more) {
                    switch (op) {
                        case 'move_node':
                            if (node.id == "source") { return false; }
                            return true;
                    }
                },
                'data' : <?=json_encode($rServerTree)?>
            }, "plugins" : [ "dnd" ]
            });
            
            $("#stream_form").submit(function(e){
                <?php if (!isset($_GET["import"])) { ?>
                if ($("#stream_display_name").val().length == 0) {
                    e.preventDefault();
                    $.toast("Enter a stream name.");
                }
                <?php } else { ?>
                if (($("#m3u_file").val().length == 0) && ($("#m3u_url").val().length == 0)) {
                    e.preventDefault();
                    $.toast("Please select a M3U file to upload or enter an URL.");
                }
                <?php } ?>
                $("#server_tree_data").val(JSON.stringify($('#server_tree').jstree(true).get_json('#', {flat:true})));
                rPass = false;
                $.each($('#server_tree').jstree(true).get_json('#', {flat:true}), function(k,v) {
                    if (v.parent == "source") {
                        rPass = true;
                    }
                });
                if (rPass == false) {
                    e.preventDefault();
                    $.toast("Select at least one server.");
                }
            });
            
            $(document).keypress(function(event){
                if (event.which == '13') {
                    event.preventDefault();
                }
            });
            
            $("#probesize_ondemand").inputFilter(function(value) { return /^\d*$/.test(value); });
            $("#delay_minutes").inputFilter(function(value) { return /^\d*$/.test(value); });
            $("#tv_archive_duration").inputFilter(function(value) { return /^\d*$/.test(value); });
            $("form").attr('autocomplete', 'off');
            <?php if (isset($rStream)) { ?>
            $("#datatable").DataTable({
                ordering: false,
                paging: false,
                searching: false,
                processing: true,
                serverSide: true,
                bInfo: false,
                ajax: {
                    url: "./table.php",
                    "data": function(d) {
                        d.id = "streams";
                        d.stream_id = <?=$rStream["id"]?>;
                    }
                },
                columnDefs: [
                    {"className": "dt-center", "targets": [3,4,5,6]},
                    {"visible": false, "targets": [0,1,2,7]}
                ],
            });
            setTimeout(reloadStream, 5000);
            <?php } ?>
        });
        </script>
    </body>
</html>