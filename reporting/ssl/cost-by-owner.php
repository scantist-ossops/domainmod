<?php
// /reporting/ssl/cost-by-owner.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/timestamps/current-timestamp-basic.inc.php");
include("../../_includes/system/functions/check-date-format.inc.php");

$page_title = $reporting_section_title;
$page_subtitle = "SSL Certificate Cost by Owner Report";
$software_section = "reporting-ssl-cost-by-owner-report";
$report_name = "ssl-cost-by-owner-report";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ((!CheckDateFormat($new_start_date) || !CheckDateFormat($new_end_date)) || $new_start_date > $new_end_date) { 

			if (!CheckDateFormat($new_start_date)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
			if (!CheckDateFormat($new_end_date)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
			if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

			$submission_failed = "1";

		}

		$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND sslc.expiry_date between '" . $new_start_date . "' AND '" . $new_end_date . "' ";
	
}

$sql = "SELECT o.id, o.name, SUM(sslc.total_cost * cc.conversion) as total_cost, count(*) AS number_of_certs
		FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, owners AS o
		WHERE sslc.fee_id = f.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND sslc.owner_id = o.id
		  AND sslc.active NOT IN ('0')
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  " . $range_string . "
		GROUP BY name
		ORDER BY name";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

$sql_grand_total = "SELECT SUM(sslc.total_cost * cc.conversion) as grand_total, count(*) AS number_of_certs_total
					FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, owners AS o
					WHERE sslc.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND sslc.owner_id = o.id
					  AND sslc.active NOT IN ('0')
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  " . $range_string . "";
$result_grand_total = mysql_query($sql_grand_total,$connection) or die(mysql_error());
while ($row_grand_total = mysql_fetch_object($result_grand_total)) {
	$grand_total = $row_grand_total->grand_total;
	$number_of_certs_total = $row_grand_total->number_of_certs_total;
}

$temp_input_amount = $grand_total;
$temp_input_conversion = "";
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include("../../_includes/system/convert-and-format-currency.inc.php");
$grand_total = $temp_output_amount;

if ($submission_failed != "1" && $total_rows > 0) {

	if ($export == "1") {

		$result = mysql_query($sql,$connection) or die(mysql_error());
	
		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "ssl_cost_by_owner_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "ssl_cost_by_owner_report_" . $new_start_date . "--" . $new_end_date . ".csv";
		}
		include("../../_includes/system/export/header.inc.php");
	
		$row_content[$count++] = $page_subtitle;
		include("../../_includes/system/export/write-row.inc.php");
	
		fputcsv($file_content, $blank_line);

		if ($all != "1") {

			$row_content[$count++] = "Date Range:";
			$row_content[$count++] = $new_start_date;
			$row_content[$count++] = $new_end_date;

        } else {

			$row_content[$count++] = "Date Range:";
			$row_content[$count++] = "ALL";

        }
		include("../../_includes/system/export/write-row.inc.php");

		$row_content[$count++] = "Total Cost:";
		$row_content[$count++] = $grand_total;
		$row_content[$count++] = $_SESSION['default_currency'];
		include("../../_includes/system/export/write-row.inc.php");

		$row_content[$count++] = "Number of SSL Certs:";
		$row_content[$count++] = $number_of_certs_total;
		include("../../_includes/system/export/write-row.inc.php");

		fputcsv($file_content, $blank_line);

		$row_content[$count++] = "Owner";
		$row_content[$count++] = "SSL Certs";
		$row_content[$count++] = "Cost";
		$row_content[$count++] = "Per Cert";
		include("../../_includes/system/export/write-row.inc.php");

		if (mysql_num_rows($result) > 0) {
	
			while ($row = mysql_fetch_object($result)) {
	
				$per_cert = $row->total_cost / $row->number_of_certs;
		
				$temp_input_amount = $per_cert;
				$temp_input_conversion = "";
				$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
				$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
				$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
				include("../../_includes/system/convert-and-format-currency.inc.php");
				$per_cert = $temp_output_amount;
	
				$temp_input_amount = $row->total_cost;
				$temp_input_conversion = "";
				$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
				$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
				$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
				include("../../_includes/system/convert-and-format-currency.inc.php");
				$row->total_cost = $temp_output_amount;
	
				$row_content[$count++] = $row->name;
				$row_content[$count++] = $row->number_of_certs;
				$row_content[$count++] = $row->total_cost;
				$row_content[$count++] = $per_cert;
				include("../../_includes/system/export/write-row.inc.php");
	
			}
	
		}
	
		include("../../_includes/system/export/footer.inc.php");

	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?> :: <?php echo $page_subtitle; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php include("../../_includes/layout/reporting-block.inc.php"); ?>
<?php include("../../_includes/layout/table-export-top.inc.php"); ?>
    <form name="export_ssl_form" method="post" action="<?php echo $PHP_SELF; ?>">
        <a href="<?php echo $PHP_SELF; ?>?all=1">View All</a> or Expiring Between
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_start_date\""; } ?>> 
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_end_date\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php
if ($submission_failed != "1" && $total_rows > 0) { ?>

	<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
	<BR>
    <?php if ($all != "1") { ?>
	    <strong>Date Range:</strong> <?php echo $new_start_date; ?> - <?php echo $new_end_date; ?><BR><BR>
    <?php } else { ?>
	    <strong>Date Range:</strong> ALL<BR><BR>
    <?php } ?>

    <strong>Total Cost:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['default_currency']; ?><BR><BR>
    <strong>Number of SSL Certs:</strong> <?php echo $number_of_certs_total; ?><BR>
    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Owner</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">SSL Certs</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Cert</font></td>
    </tr>

	<?php
	while ($row = mysql_fetch_object($result)) {
		
		$per_cert = $row->total_cost / $row->number_of_certs;
		
		$temp_input_amount = $per_cert;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$per_cert = $temp_output_amount;

		$temp_input_amount = $row->total_cost;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$row->total_cost = $temp_output_amount; ?>
	
		<tr class="main_table_row_active">
			<td class="main_table_cell_active"><a class="invisiblelink" href="../../ssl-certs.php?oid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a></td>
			<td class="main_table_cell_active"><a class="invisiblelink" href="../../ssl-certs.php?oid=<?php echo $row->id; ?>"><?php echo $row->number_of_certs; ?></a></td>
			<td class="main_table_cell_active"><?php echo $row->total_cost; ?></td>
			<td class="main_table_cell_active"><?php echo $per_cert; ?></td>
		</tr><?php

	}
		?>
    </table><?php

} 
?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
