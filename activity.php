<?php

if ($_GET['get']) {
	header('Content-Type: text/plain');
	
	if ($_GET['get'] == 'dates') {
		foreach (scandir('logs/narc/') as $file) {
			if ($file === '.' || $file === '..') continue;
			
			echo substr($file, 11, 8), "\n";
		}
	}
} else {
?>
<!DOCTYPE html>
<html>
	<head>
		<title>NARC Admin Activity</title>
		
		<style>
			html, body, #chart-wrapper {
				height: 100%;
				font-family: sans-serif;
			}
			
			.chart {
				height: 70%;
			}
		</style>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
		<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	</head>
	<body>
		<h1><center>NARC Admin Activity<center></h1>
		<div id="chart-wrapper"></div>
		
		<script>
			google.charts.load("current", {packages:["timeline"]});
			google.charts.setOnLoadCallback(startDraw);
			
			function startDraw() {
				
				$.ajax({
					url: "?get=dates", 
					success: function(data) {
						var dates = data.trim().split("\n");
						
						for (var i=0; i<dates.length; i++) {
							var split_date = dates[i].split("_");
							var month = parseInt(split_date[0])-1;
							var date  = parseInt(split_date[1]);
							var year  = parseInt("20" + split_date[2]);
							
							var d = new Date(year, month, date);
							
							var id = "chart" + date.toString() + month.toString() + year.toString();
							
							var log_url = 'https://srv.nwrp.eu/logs/narc/server_log_' + dates[i] + '.txt';
							
							$("#chart-wrapper").append('<h2><center>' + d.toLocaleDateString() + '<center></h2><div class="chart" id="' + id +'"></div>');
							
							$.get({
								url: log_url,
								success: function(logs) {
									drawActivity(id, getAdminsActivity(logs, i===dates.length-1))
								},
								async: false,
							});
						}
					},
					async: false,
				});
				
				
			}
			
			var logged_in_list = {};
			
			function getAdminsActivity(logs, isLast) {
				var regex = /^ ?(\d\d:\d\d:\d\d) - (.+) has (left|joined) the game with ID: (\d+)(?: and has (administrator) rights)?/gm;
				
				var activity = [];
				
				
				var match;
				while (match = regex.exec(logs)) {
					var time = match[1].split(":");
					var name = match[2];
					var type = match[3];
					var id   = parseInt(match[4]);
					var admin = match[5] === "administrator";
					
					
					var dateObj = new Date(0, 0, 0, time[0], time[1], time[2]);
					
					if (type == "joined" && admin /*&& id !== 0*/) {
						if (!(id in logged_in_list)) {
							logged_in_list[id] = [name, dateObj];
						} else {
							var startDate = logged_in_list[id][1];
							if (startDate > dateObj) {
								startDate = new Date(0,0,0,0,0,0);
							}
							
							activity.push([id.toString(), logged_in_list[id][0], startDate, dateObj]);
							
							logged_in_list[id] = [name, dateObj];
						}
					} else {
						if (id in logged_in_list) {
							
							var startDate = logged_in_list[id][1];
							if (startDate > dateObj) {
								startDate = new Date(0,0,0,0,0,0);
							}
							
							activity.push([id.toString(), logged_in_list[id][0], startDate, dateObj]);
							delete logged_in_list[id];
						}
					}
				}
				
				for (id in logged_in_list) {
					if (logged_in_list.hasOwnProperty(id)) {
						var startDate = logged_in_list[id][1];
						if (startDate > dateObj) {
							startDate = new Date(0,0,0,0,0,0);
						}
						
						var endDate = new Date(0,0,0,24,0,0);
						if (isLast) {
							
							var options = {
								timeZone: 'Europe/Berlin',
								hour: 'numeric', minute: 'numeric', second: 'numeric',
							};
							formatter = new Intl.DateTimeFormat([], options)
							
							var curTime = formatter.format(new Date()).split(":");
							
							endDate = new Date(0,0,0,curTime[0],curTime[1],curTime[2]);
						}
						
						activity.push([id.toString(), logged_in_list[id][0], startDate, endDate]);
						
						logged_in_list[id] = [logged_in_list[id][0], new Date(0,0,0,0,0,0)];
					}
				}
				
				return activity;
			} 
			
			function drawActivity(id, activity) {
				var container = document.getElementById(id);
				var chart = new google.visualization.Timeline(container);
				var dataTable = new google.visualization.DataTable();
				dataTable.addColumn({ type: 'string', id: 'Id' });
				dataTable.addColumn({ type: 'string', id: 'Name' });
				dataTable.addColumn({ type: 'date', id: 'Start' });
				dataTable.addColumn({ type: 'date', id: 'End' });
				dataTable.addRows(activity);
				
				var options = {
					hAxis: {
						format: 'HH:mm',
						minValue: new Date(0,0,0,0,0,0),
						maxValue: new Date(0,0,0,24,0,0),
					},
					timeline: { colorByRowLabel: true }
				};
				
				chart.draw(dataTable, options);
			}
		</script>
	</body>
</html>
<?php
}
?>
