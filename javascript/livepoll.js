var livepoll = new function() {
	var socket;
	var isteacher;
	var curquestion = -1;
	var curqresults;
	var curstate = 0;
	var working = false;
	var LPtimer;
	var LPtimestart = 0;
	var qdata = [];
	var results = [];
	var stucnt=0, teachcnt=0;
	var settings = {
		showqonload: true,
		showreslive: false,
		showresonclose: true,
		showansonclose: true
		};
	var counter = 0;
	window.countertimer = null;

	this.init = function(server, room, timestamp, sig) {
		isteacher = room.match(/teacher/);

		var querystr = 'room='+room+'&now='+timestamp+'&sig='+encodeURIComponent(sig);
		socket = io('https://'+server+':3000', {query: querystr});
		socket.on('livepoll usercount', updateUsercount);

		if (isteacher) {
			socket.on('livepoll qans', addResult);
			setupInstructorPanel();
		} else {
			socket.on('livepoll show', showHandler);
		}
	}

	this.restoreState = function(qn, action, seed, startt) {
		if (stucnt==0) { //haven't connected yet
			setTimeout(function(){livepoll.restoreState(qn,action,seed,startt)}, 100);
		} else if (teachcnt==0) {
			//no teachers; skip restore
		} else {
			showHandler({qn: qn, action: action, seed:seed, startt:startt});
		}
	}

	this.showSettings = function() {
		$("#LPperqsettings").hide();
		$("#LPsettings").show();
	}

	function hideSettings() {
		if (curquestion != -1) {
			$("#LPperqsettings").show();
		}
		$("#LPsettings").hide();
	}

	this.updateSettings = function() {
		settings.showqonload = $("#LPsettings-dispq").is(":checked");
		settings.showreslive = $("#LPsettings-liveres").is(":checked");
		settings.showresonclose = $("#LPsettings-resafter").is(":checked");
		settings.showansonclose = $("#LPsettings-showans").is(":checked");
		if ($("#LPsettings-docountdown").is(":checked")) {
			$("#LPsettings-timelimitwrap").show();
			settings.countdown = $("#LPsettings-timelimit").val();
		} else {
			$("#LPsettings-timelimitwrap").hide();
			settings.countdown = 0;
		}
	}

	function setupInstructorPanel() {
		$(function() {
			$("a[data-showq]").on("click", showQuestionHandler);
			$("#LPshowqchkbox").on("change", function() {
				$("#livepollqcontent").toggle($("#LPshowqchkbox").is(":checked"));
			});
			$("#LPshowrchkbox").on("change", function() {
				$("#livepollrcontent").toggle($("#LPshowrchkbox").is(":checked"));
			});
			$("#LPshowanschkbox").on("change", showAnsIfAllowed);
			$("#LPstartq").on("click", startQuestion);
			$("#LPstopq").on("click", stopQuestionHandler);
			$("#LPhidesettings").on("click", hideSettings);
			$(window).on("beforeunload", function() {stopQuestion(0);});
		});
	}

	function showAnsIfAllowed() {
		if ($("#LPshowanschkbox").is(":checked") && (curstate==3 || curstate==4)) {
			$(".LPcorrect").addClass("LPshowcorrect");
			$(".LPwrong").addClass("LPshowwrong");
			if (curstate==3 && curquestion>=0) {
				$.ajax({
					url: assesspostbackurl+'&action=livepollstopq&qn='+curquestion+'&newstate=4',
					retryCount: 0,
					retryLimit: 61,
					error : function(xhr, textStatus, errorThrown ) {
						this.retryCount++;
						if (this.retryCount <= this.retryLimit) {
							var ajaxObject = this;
							window.setTimeout(function(){
								$.ajax(ajaxObject);
							},Math.floor(250+Math.random()*750));
							return;
						} else{
						}
					},
					success: function (data) {
						curstate = 4;
					}
				});
			}
		} else {
			$(".LPcorrect").removeClass("LPshowcorrect");
			$(".LPwrong").removeClass("LPshowwrong");
			if (curstate==4 && curquestion>=0) {
				$.ajax({
					url: assesspostbackurl+'&action=livepollstopq&qn='+curquestion+'&newstate=3',
					retryCount: 0,
					retryLimit: 61,
					error : function(xhr, textStatus, errorThrown ) {
						this.retryCount++;
						if (this.retryCount <= this.retryLimit) {
							var ajaxObject = this;
							window.setTimeout(function(){
								$.ajax(ajaxObject);
							},Math.floor(250+Math.random()*750));
							return;
						} else{
						}
					},
					success: function (data) {
						curstate = 3;
					}
				});
			}
		}
	}

	function showHandler(data) {
		clearInterval(LPtimer);
		LPtimestart = 0;
		//handle question show
		var qn = data.qn;
		if (data.action=='showq') {
			LPtimestart = data.startt;
			$.ajax({
				url: assesspostbackurl+'&action=livepollshowq&qn='+data.qn+'&seed='+data.seed,
				retryCount: 0,
				retryLimit: 61,
				error : function(xhr, textStatus, errorThrown ) {
					this.retryCount++;
					if (this.retryCount <= 4) {
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)));
					} else if (this.retryCount <= 15){
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />This takes a bit longer than it should but we're still working on it."));
					} else if (this.retryCount <= 60){
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />Our servers are very busy at the moment but we're still working on it."));
					} else {
						$("#livepollqcontent").html(_("Our servers are very busy at the moment please refresh the page and try again."));
					}
					if (this.retryCount <= this.retryLimit) {
						var ajaxObject = this;
						window.setTimeout(function(){
							$.ajax(ajaxObject);
						},Math.floor(250+Math.random()*750));
						return;
					} else{
					}
				},
				success: function (data) {
					var parsed = preProcess(data);
					var button = '<div><span id="livepollsubmit"><button type="button" onclick="livepoll.submitQuestion('+qn+')">'+_("Submit")+'</button> <span id="livepollsubmitmsg"></span></span></div>';
					$("#livepollqcontent").html(parsed.html+button);
					postProcess('livepollqcontent',parsed.code);
					curquestion = qn;
					curstate = 2;
					LPtimer = setInterval(LPtimerkeeper,1000);
				}
			});
		} else if (data.action=='3') {
			if (curstate==2) {
				$("#livepollsubmit").remove();
				$("#livepollqcontent").find("input").prop("disabled",true);
				curstate = 3;
			} else {
				$.ajax({
					url: assesspostbackurl+'&action=livepollshowq&qn='+data.qn,
					retryCount: 0,
					retryLimit: 61,
					error : function(xhr, textStatus, errorThrown ) {
						this.retryCount++;
						if (this.retryCount <= 4) {
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)));
						} else if (this.retryCount <= 15){
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />This takes a bit longer than it should but we're still working on it."));
						} else if (this.retryCount <= 60){
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />Our servers are very busy at the moment but we're still working on it."));
						} else {
							$("#livepollqcontent").html(_("Our servers are very busy at the moment please refresh the page and try again."));
						}
						if (this.retryCount <= this.retryLimit) {
							var ajaxObject = this;
							window.setTimeout(function(){
								$.ajax(ajaxObject);
							},Math.floor(250+Math.random()*750));
							return;
						} else{
							$("#livepollqcontent").html(_("Error, please try again."));
						}
					},
					success: function (data) {
						var parsed = preProcess(data);
						$("#livepollqcontent").html(parsed.html).find("input").prop("disabled",true);
						postProcess('livepollqcontent',parsed.code);
						curquestion = qn;
						curstate = 3;
					}
				});
			}
			curquestion = qn;
		} else if (data.action=='4') {
			if (curstate==2) {
				$("#livepollsubmit").remove();
			}
			if (curstate != 4) {
				$.ajax({
					url: assesspostbackurl+'&action=livepollshowqscore&qn='+data.qn,
					retryCount: 0,
					retryLimit: 61,
					error : function(xhr, textStatus, errorThrown ) {
						this.retryCount++;
						if (this.retryCount <= 4) {
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)));
						} else if (this.retryCount <= 15){
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />This takes a bit longer than it should but we're still working on it."));
						} else if (this.retryCount <= 60){
							$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />Our servers are very busy at the moment but we're still working on it."));
						} else {
							$("#livepollqcontent").html(_("Our servers are very busy at the moment please refresh the page and try again."));
						}
						if (this.retryCount <= this.retryLimit) {
							var ajaxObject = this;
							window.setTimeout(function(){
								$.ajax(ajaxObject);
							},Math.floor(250+Math.random()*750));
							return;
						} else{
							$("#livepollqcontent").html(_("Error, please try again."));
						}
					},
					success: function (data) {
						var parsed = preProcess(data);
						$("#livepollqcontent").html(parsed.html);
						postProcess('livepollqcontent',parsed.code);
						curquestion = qn;
						curstate = 4;
					}
				});
			}
		} else if (data.action=='0') {
			$("#livepollqcontent").html(_('Waiting for the instructor to start a question'));
			curstate = 0;
		}

	}

	function updateUsercount(data) {
		//receive usercount data
		stucnt = data.cnt;
		teachcnt = data.teachcnt;
		if (isteacher) {
			$("#livepollactivestu").html(data.cnt+" " +(data.cnt==1?_('student'):_('students')));
		} else if (data.teachcnt==0) {
			showHandler({action: 0, qn: -1});
		}
	}

	function addResult(data) {
		//add question result data
		if (!results.hasOwnProperty(curquestion)) {
			results[curquestion] = [];
		}
		results[curquestion][data.user] = data;
		updateResults();
	}
	this.loadResults = function(data) {
		results = data;
	}

	function updateResults() {
		var datatots = {};
		var scoredat = {};
		var multipartdata = [];
		if (qdata[curquestion].choices.length>0) {
			for (i=0;i<qdata[curquestion].choices.length;i++) {
				datatots[i] = 0;
				scoredat[i] = 0;
			}
		}
		if (qdata[curquestion].anstypes=="choices" || qdata[curquestion].anstypes=="multans") {
			if (qdata[curquestion].anstypes=="choices") {
				var anss = qdata[curquestion].ans.split(/\s+or\s+/);
			} else if (qdata[curquestion].anstypes=="multans") {
				var anss = qdata[curquestion].ans.split(/\s*,\s*/);
			}
			for (i=0;i<anss.length;i++) {
				scoredat[anss[i]] = 1;
			}
		}
		if (qdata[curquestion].anstypes.includes(",")) {
			for(i in qdata[curquestion].anstypes.split(",")){
				multipartdata.push([]);
			}
		} else if (qdata[curquestion].anstypes=="matching") {
			for(i in qdata[curquestion].choices){
				var tmp = [];
				for(i in qdata[curquestion].choices){
					tmp.push(0);
				}
				multipartdata.push(tmp);
			}
		}
		var ischoices = false;
		var rescnt = 0;
		var condenseddrawarr = [];
		var condenseddraw;
		var drawinitstack = [];
		//group and total results
		for (i in results[curquestion]) {
			ischoices = (qdata[curquestion].anstypes=="choices" || qdata[curquestion].anstypes=="multans");
			if (ischoices) {
				pts = results[curquestion][i].ans.split("$!$");
				subpts = pts[1].split("|");
			} else if (qdata[curquestion].anstypes=="numfunc") {
        		pts = results[curquestion][i].ans.split("$f$");
				subpts = [pts[0]];
			} else if (qdata[curquestion].anstypes.includes(",")) {
				subpts = results[curquestion][i].ans.split("&");
				for(var j=0;j<subpts.length;j++){
					if(subpts[j].includes("$!$")){
						subpts[j] = subpts[j].split("$!$")[1].split("|");
					} else if(subpts[j].includes("$#$")){
						subpts[j] = [subpts[j].split("$#$")[0]];
					}
					if(qdata[curquestion].anstypes.split(",")[j].match(/calc/) || qdata[curquestion].anstypes.split(",")[j]=="numfunc"){
						subpts[j] = ["`"+subpts[j]+"`"];
					}
				}
				
				for (var j=0;j<subpts.length;j++) {
					if (!multipartdata[j].hasOwnProperty(subpts[j])) {
						multipartdata[j].push(subpts[j]);
					}
				}
			} else if(qdata[curquestion].anstypes=="matching"){
				subpts = results[curquestion][i].ans.split("$!$")[0].split("|");
				for(var j=0;j<subpts.length;j++){
					if(multipartdata.length <= j){
						var tmp = [];
						for(i in qdata[curquestion].choices){
							tmp.push(0);
						}
						multipartdata.push(tmp);
					}
					multipartdata[j][subpts[j]]++;
				}
			} else {
				pts = results[curquestion][i].ans.split("$#$");
				subpts = [pts[0]];
			}
			if (!qdata[curquestion].anstypes.includes(",") && (qdata[curquestion].anstypes.match(/calc/) || qdata[curquestion].anstypes=="numfunc")) {
				subpts[0] = "`"+subpts[0]+"`";
			}
			if (qdata[curquestion].anstypes=="draw") {
				condenseddraw = condenseDraw(subpts[0]);
				if (!condenseddrawarr.hasOwnProperty(condenseddraw)) {
					condenseddrawarr[condenseddraw] = subpts[0];
				}
			}
			for (var j=0;j<subpts.length;j++) {
				if (qdata[curquestion].anstypes=="draw" && datatots.hasOwnProperty(condenseddrawarr[condenseddraw])) {
					datatots[condenseddrawarr[condenseddraw]] += 1;
				} else if (datatots.hasOwnProperty(subpts[j])) {
					datatots[subpts[j]] += 1;
				} else {
					datatots[subpts[j]] = 1;
					scoredat[subpts[j]] = results[curquestion][i].score;
				}
			}
			rescnt++;
		}
		//pre-explode initpts for draw
		var initpts,drawwidth,drawheight;
		if (qdata[curquestion].anstypes=="draw") {
			var initpts = qdata[curquestion].drawinit.replace(/"|'/g,'').split(",");
			for (var j=1;j<initpts.length;j++) {
				initpts[j] *= 1;  //convert to number
			}
		}
		//matching find freq
		if(qdata[curquestion].anstypes=="matching"){
			for (var j=0;j<multipartdata.length;j++){
				var tmpCount = 0;
				for(var jj=0;jj<multipartdata[j].length;jj++){
					tmpCount += multipartdata[j][jj];
				}
				datatots[j] = tmpCount;
			}
		}
		var out = '';
		var maxfreq = 1;
		for (i in datatots) {
			if (datatots[i]>maxfreq) {maxfreq = datatots[i];}
		}
		if (qdata[curquestion].choices.length>0) {
			if (qdata[curquestion].initrdisp && qdata[curquestion].randkeys) {
				for (i=0;i<qdata[curquestion].randkeys.length;i++) {
					partn = qdata[curquestion].randkeys[i];
					$("#LPresval"+partn).text(datatots[partn]);
					$("#LPresbar"+partn).width(Math.round(100*datatots[partn]/maxfreq) + "%");
				}
			} else {
				out += '<table class=\"LPres\"><thead><tr><th>'+_("Answer")+'</th><th style="min-width:10em">' + _("Frequency")+'</th></tr></thead><tbody>';
				if (qdata[curquestion].randkeys){
					for (i=0;i<qdata[curquestion].randkeys.length;i++) {
						partn = qdata[curquestion].randkeys[i];
						out += '<tr class="';
						if (scoredat[partn]>0) {
							out += "LPcorrect";
						} else {
							out += "LPwrong";
						}
						out += '"><td>';
						out += qdata[curquestion].choices[partn];
						out += '</td><td><span class="LPresbarwrap"><span class="LPresbar" id="LPresbar'+partn+'" style="width:' + Math.round(100*datatots[partn]/maxfreq) +'%;">';
						out += '<span class="LPresval" id="LPresval'+partn+'">'+ datatots[partn] +'</span>';
						out += '</span></span></td></tr>';
					}
				}
				else{
					//Matching type
					out = '<table class=\"LPres\"><thead><tr><th style="min-width: 8em;">'+_("Question")+'</th><th colspan="'+multipartdata[0].length+'" style="min-width:10em;width: 100%">' + _("Frequencies")+'</th></tr></thead><tbody style="text-align: center;">';
					for (var j=0;j<multipartdata.length;j++){
						out += '<tr>';
						out += "<td>Question "+(j+1)+"</td>";
						for (var i=0;i<multipartdata[j].length;i++) {
							out += '<td><span class="LPresbarwrap"><span class="LPresbar" id="LPresbar'+multipartdata[j]+"-"+multipartdata[j][i]+'" style="width:' + Math.round(100*multipartdata[j][i]/datatots[j]) +'%;">';
							out += '<span class="LPresval" id="LPresval'+multipartdata[j]+'">'+ multipartdata[j][i] +'</span>';
							out += '</span></span></td>';
						}
						out += '</tr>';
					}
				}
				out += "</tbody></table>";
				$("#livepollrcontent").html(out);
				qdata[curquestion].initrdisp = true;
				if (usingASCIIMath) {
					rendermathnode(document.getElementById("livepollrcontent"));
				}
				if (usingASCIISvg) {
					setTimeout("drawPics()",100);
				}
			}
		} else if (qdata[curquestion].anstypes=="draw" && initpts[11]==0) { //draw, no snap
			var sortedkeys = getSortedKeys(datatots);
			for (var i=0;i<sortedkeys.length;i++) {
				drawwidth = initpts[6];
				drawheight = initpts[7];
				initpts.unshift("LP"+curquestion+"-"+i);
				//rewrite this at some point;
				var la = sortedkeys[i].replace(/\(/g,"[").replace(/\)/g,"]");
				la = la.split(";;")
				if  (la[0]!='') {
					la[0] = '['+la[0].replace(/;/g,"],[")+"]";
				}
				la = '[['+la.join('],[')+']]';
				canvases["LP"+curquestion+"-"+i] = initpts;
				drawla["LP"+curquestion+"-"+i] = JSON.parse(la);

				out += '<div class="';
				if (scoredat[sortedkeys[i]]>0) {
					out += "LPcorrect";
				} else {
					out += "LPwrong";
				}
				out += '">';
				out += '<canvas class="drawcanvas" id="canvasLP'+curquestion+"-"+i+'" width='+drawwidth+' height='+drawheight+'></canvas>';
				out += '<input type="hidden" id="qnLP'+curquestion+"-"+i+'"/></div>';
			}
			$("#livepollrcontent").html('<div class="LPdrawgrid" >'+out+'</div>');
			for (var i=0;i<sortedkeys.length;i++) {
				imathasDraw.initCanvases("LP"+curquestion+"-"+i);
			}
		} else if (qdata[curquestion].anstypes=="file") { //file upload
			out += '<div class=\"LPres floatleft\"><h2>'+_("Answers")+'</h2>';
			var sortedkeys = getSortedKeys(datatots);
			for (var i=0;i<sortedkeys.length;i++) {
				out += '<img id="img'+i+'" style="min-width: 10em; max-width: 25%; transform: rotate(0deg);" aria-hidden="false" onclick="rotateimg(this)" ';
				var filename = sortedkeys[i].replace("@FILE:","").replace("@","");
				out += 'src="'+fileprefix+filename+'" alt="Student uploaded image">';
			}
			out += '</div>';
			$("#livepollrcontent").html(out);
		} else if (qdata[curquestion].anstypes.includes(",")) {
			for (var j=0;j<multipartdata.length;j++){
				var sortedkeys = multipartdata[j].sort();
				//console.log(multipartdata[j]);
				out += '<table class=\"LPres\" style=\"float: left;margin-right: 1em;margin-bottom: .2em;\"><thead><tr><th>'+_("Answer")+'</th><th style="min-width:10em">'+_("Frequency")+'</th></tr></thead><tbody>';
				for (var i=0;i<sortedkeys.length;i++) {
					out += '<tr class="';
					if (scoredat[sortedkeys[i]]>0) {
						out += "LPcorrect";
					} else {
						out += "LPwrong";
					}
					out += '"><td>';
					if (qdata[curquestion].anstypes=="draw") {
						//initpts = qdata[curquestion].drawinit.replace(/"|'/g,'').split(",");
						//for (var j=1;j<initpts.length;j++) {
						//	initpts[j] *= 1;  //convert to number
						//}
						drawwidth = initpts[6];
						drawheight = initpts[7];

						//rewrite this at some point;
						var la = sortedkeys[i].replace(/\(/g,"[").replace(/\)/g,"]");
						la = la.split(";;")
						if  (la[0]!='') {
							la[0] = '['+la[0].replace(/;/g,"],[")+"]";
						}
						la = '[['+la.join('],[')+']]';
						canvases["LP"+curquestion+"-"+i] = initpts.slice();
						canvases["LP"+curquestion+"-"+i].unshift("LP"+curquestion+"-"+i);
						drawla["LP"+curquestion+"-"+i] = JSON.parse(la);

						out += '<canvas class="drawcanvas" id="canvasLP'+curquestion+"-"+i+'" width='+drawwidth+' height='+drawheight+'></canvas>';
						out += '<input type="hidden" id="qnLP'+curquestion+"-"+i+'"/>';
					} else {
						out += sortedkeys[i];
					}
					out += '</td><td><span class="LPresbarwrap"><span class="LPresbar" id="LPresbar'+sortedkeys[i]+'" style="width:' + Math.round(100*datatots[sortedkeys[i]]/maxfreq) +'%;">';
					out += '<span class="LPresval" id="LPresval'+sortedkeys[i]+'">'+ datatots[sortedkeys[i]] +'</span>';
					out += '</span></span></td></tr>';
					//out += "</td><td>"+datatots[sortedkeys[i]]+"</td></tr>";
				}
				out += "</tbody></table>";
			}

			$("#livepollrcontent").html(out);
			if (usingASCIIMath) {
				rendermathnode(document.getElementById("livepollrcontent"));
			}
			if (usingASCIISvg) {
				setTimeout("drawPics()",100);
			}
			for (var i=0;i<sortedkeys.length;i++) {
				imathasDraw.initCanvases("LP"+curquestion+"-"+i);
			}
		} else if (qdata[curquestion].anstypes=="heatmap"){
			var ansContainer = $('.questionContainer').clone();
			out += ansContainer.html();
			$("#livepollrcontent").html(out);

			// Display student data (need to wait until picture is loaded)
			$('#livepollrcontent .heatmap img').attr('src',$('#livepollrcontent .heatmap img').attr('src'));
			var heatmapInstance;
			$('#livepollrcontent .heatmap img').on('load', function() {
				heatmapInstance = h337.create({
					container: document.querySelector('#livepollrcontent .heatmap')
				});
				var canvas = document.querySelector('#livepollrcontent .heatmap-canvas');
				var ctx = canvas.getContext('2d');
				sortedkeys = getSortedKeys(datatots);
				for (var i=0;i<sortedkeys.length;i++) {
					coordinates = sortedkeys[i].split(',');
					// convert from permille
					ptX = coordinates[0]/1000*heatmapInstance._renderer._width;
					ptY = coordinates[1]/1000*heatmapInstance._renderer._width;
					drawX(ctx,parseInt(ptX),parseInt(ptY));
				}
			});
			$("#LPshowrchkbox").on("change", function() {
				if(typeof heatmapInstance !== 'undefined' && ! heatmapInstance._renderer._width > 0 && $("#livepollrcontent .heatmap").length > 0){
					$('#livepollrcontent .heatmap canvas').remove();

					heatmapInstance = h337.create({
						container: document.querySelector('#livepollrcontent .heatmap')
					});
					var canvas = document.querySelector('#livepollrcontent .heatmap-canvas');
					var ctx = canvas.getContext('2d');
					sortedkeys = getSortedKeys(datatots);
					for (var i=0;i<sortedkeys.length;i++) {
						coordinates = sortedkeys[i].split(',');
						// convert from permille
						ptX = coordinates[0]/1000*heatmapInstance._renderer._width;
						ptY = coordinates[1]/1000*heatmapInstance._renderer._width;
						drawX(ctx,parseInt(ptX),parseInt(ptY));
					}
				}
			});

		} else {
			var sortedkeys = getSortedKeys(datatots);
			out += '<table class=\"LPres\"><thead><tr><th>'+_("Answer")+'</th><th style="min-width:10em">'+_("Frequency")+'</th></tr></thead><tbody>';
			for (var i=0;i<sortedkeys.length;i++) {
				out += '<tr class="';
				if (scoredat[sortedkeys[i]]>0) {
					out += "LPcorrect";
				} else {
					out += "LPwrong";
				}
				out += '"><td>';
				if (qdata[curquestion].anstypes=="draw") {
					//initpts = qdata[curquestion].drawinit.replace(/"|'/g,'').split(",");
					//for (var j=1;j<initpts.length;j++) {
					//	initpts[j] *= 1;  //convert to number
					//}
					drawwidth = initpts[6];
					drawheight = initpts[7];

					//rewrite this at some point;
					var la = sortedkeys[i].replace(/\(/g,"[").replace(/\)/g,"]");
					la = la.split(";;")
					if  (la[0]!='') {
						la[0] = '['+la[0].replace(/;/g,"],[")+"]";
					}
					la = '[['+la.join('],[')+']]';
					canvases["LP"+curquestion+"-"+i] = initpts.slice();
					canvases["LP"+curquestion+"-"+i].unshift("LP"+curquestion+"-"+i);
					drawla["LP"+curquestion+"-"+i] = JSON.parse(la);

					out += '<canvas class="drawcanvas" id="canvasLP'+curquestion+"-"+i+'" width='+drawwidth+' height='+drawheight+'></canvas>';
					out += '<input type="hidden" id="qnLP'+curquestion+"-"+i+'"/>';
				} else {
					out += sortedkeys[i];
				}
				out += '</td><td><span class="LPresbarwrap"><span class="LPresbar" id="LPresbar'+sortedkeys[i]+'" style="width:' + Math.round(100*datatots[sortedkeys[i]]/maxfreq) +'%;">';
				out += '<span class="LPresval" id="LPresval'+sortedkeys[i]+'">'+ datatots[sortedkeys[i]] +'</span>';
				out += '</span></span></td></tr>';
				//out += "</td><td>"+datatots[sortedkeys[i]]+"</td></tr>";
			}
			out += "</tbody></table>";
			$("#livepollrcontent").html(out);
			if (usingASCIIMath) {
				rendermathnode(document.getElementById("livepollrcontent"));
			}
			if (usingASCIISvg) {
				setTimeout("drawPics()",100);
			}
			for (var i=0;i<sortedkeys.length;i++) {
				imathasDraw.initCanvases("LP"+curquestion+"-"+i);
			}

		}
		$("#livepollrcnt").html(rescnt+" "+(rescnt==1?_("result"):_("results"))+" "+_("received."));
	}

	function getSortedKeys(obj) {
		var keys = []; for(var key in obj) keys.push(key);
		return keys.sort(function(a,b){return obj[b]-obj[a]});
	}

	function showQuestionHandler(e) {
		e.preventDefault();
		var qn = $(this).attr("data-showq")*1;
		showQuestion(qn);
		return false;
	}

	this.forceRegen = function(qn) {
		showQuestion(qn,true);
	}

	function showQuestion(qn, forceregen) {
		if (!working) {
			if (qn==curquestion && typeof forceregen == 'undefined') {
				//redisplaying the same q?  just ignore it
				return;
			} else if (curquestion != -1 && curstate==2) {
				//if another question is currently open, stop it
				stopQuestion(3);
			}

			$("#LPstopq").hide();
			$("#LPstartq").hide();
			LPtimestart = 0;
			clearInterval(LPtimer);
			$("#livepolltopright").text("");
			$("#LPqnumber").text(_("Question") + " "+(qn+1));
			$("#livepollqcontent").html(_("Loading..."));
			$("#livepollrcontent").html("");

			if (typeof forceregen != 'undefined') {
				var regenstr = '&forceregen=true';
			} else {
				var regenstr = '';
			}

			working = true;
			$.ajax({
				url: assesspostbackurl+'&action=livepollshowq&includeqinfo=true&qn='+qn+regenstr,
				dataType: "json",
				retryCount: 0,
				retryLimit: 61,
				error : function(xhr, textStatus, errorThrown ) {
					this.retryCount++;
					if (this.retryCount <= 4) {
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)));
					} else if (this.retryCount <= 15){
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />This takes a bit longer than it should but we're still working on it."));
					} else if (this.retryCount <= 60){
						$("#livepollqcontent").html(_("Loading"+".".repeat(this.retryCount%4)+"<br />Our servers are very busy at the moment but we're still working on it."));
					} else {
						$("#livepollqcontent").html(_("Our servers are very busy at the moment please refresh the page and try again."));
						working = false;
					}
					if (this.retryCount <= this.retryLimit) {
						var ajaxObject = this;
						window.setTimeout(function(){
							$.ajax(ajaxObject);
						},Math.floor(250+Math.random()*750));
						return;
					} else{
						$("#livepollqcontent").html(_("Error, please try again."));
					}
				},
				success: function (data) {
					console.log("Success");
					curquestion = qn;
					curstate = 1;

					$("#LPshowqchkbox").prop("checked", settings.showqonload).trigger("change");
					$("#LPshowrchkbox").prop("checked", settings.showreslive).trigger("change");
					$("#LPshowanschkbox").prop("checked", settings.showansonclose).trigger("change");
					$("#LPshowansmsg").text(_("Show Answers When Closed"));
					hideSettings();

					var parsed = preProcess(data.html);
					$("#livepollqcontent").html(parsed.html);
					postProcess('livepollqcontent',parsed.code);
					//$(".sabtn").hide();
					if(data.anstypes=="file"){
						$("#livepollqcontent").append('<p><a href="#" onclick="livepoll.forceRegen('+qn+');return false;">' + _("Hide results and generate a new version of this question")+'</a></p>');
					} else {
						$("#livepollqcontent").append('<p><a href="#" onclick="livepoll.forceRegen('+qn+');return false;">' + _("Clear results and generate a new version of this question")+'</a></p>');
					}
					$("#LPstartq").show();
					$(".question").addClass("LPinactive");

					answer = data.ans==null?"":data.ans;
					qdata[qn] = {choices: data.choices, randkeys: data.randkeys, ans: answer.toString(), anstypes: data.anstypes, seed: data.seed, drawinit: data.drawinit, initrdisp:false};
					if (typeof forceregen != 'undefined') {
						results[qn] = [];
					}
					updateResults();
					working = false;
				  }
			});
		}

		return false;
	}

	function preProcess(resptxt) {
		var scripts = new Array();
		while(resptxt.indexOf("<script") > -1 || resptxt.indexOf("</script") > -1) {
			var s = resptxt.indexOf("<script");
			var s_e = resptxt.indexOf(">", s);
			var e = resptxt.indexOf("</script", s);
			var e_e = resptxt.indexOf(">", e);

			// Add to scripts array
			scripts.push(resptxt.substring(s_e+1, e));
			// Strip from strcode
			resptxt = resptxt.substring(0, s) + resptxt.substring(e_e+1);
		}
		return {html: resptxt, code: scripts}
	}
	function postProcess(el,scripts) {
		if (usingASCIIMath) {
			rendermathnode( document.getElementById(el));
		}
		if (usingASCIISvg) {
			setTimeout("drawPics()",100);
		}
		if (usingTinymceEditor) {
			initeditor("textareas","mceEditor");
		}
		// Loop through every script collected and eval it
		initstack.length = 0;
		for(var i=0; i<scripts.length; i++) {
		    try {
			    if (k=scripts[i].match(/canvases\[(\d+)\]/)) {
				if (typeof G_vmlCanvasManager != 'undefined') {
					scripts[i] = scripts[i] + 'G_vmlCanvasManager.initElement(document.getElementById("canvas'+k[1]+'"));';
				}
				scripts[i] = scripts[i] + "imathasDraw.initCanvases("+k[1]+");";
			    }
			    eval(scripts[i]);
		    }
		    catch(ex) {
			    // do what you want here when a script fails
		    }
		}
		for (var i=0; i<initstack.length; i++) {
		    var foo = initstack[i]();
		}
		$(window).trigger("ImathasEmbedReload");
		initcreditboxes();
	}

	function startQuestion() {
		var qn = curquestion;
		if (qn<0 || curstate==2 || working) { return;}
		$("#LPstartq").text(_("Staring Assessment..."));
		working = true;
		clearInterval(LPtimer);
		LPtimestart = Date.now();
		$.ajax({
			url: assesspostbackurl+'&action=livepollopenq&qn='+qn+'&seed='+qdata[qn].seed+'&startt='+LPtimestart,
			retryCount: 0,
			retryLimit: 61,
			error : function(xhr, textStatus, errorThrown ) {
				this.retryCount++;
				if (this.retryCount <= this.retryLimit) {
					$("#LPstartq").text(_("Staring Assessment"+".".repeat(this.retryCount%4)));
					var ajaxObject = this;
					window.setTimeout(function(){
						$.ajax(ajaxObject);
					},Math.floor(250+Math.random()*750));
					return;
				} else{
					$("#LPstartq").text(_("Start Assessment"));
					working = false;
				}
			},
			success: function (data) {
				$("#LPstartq").text(_("Start Assessment")).hide();
				$("#LPstopq").show();
				LPtimer = setInterval(LPtimerkeeper,1000);
				curstate = 2;
				showAnsIfAllowed();
				$("#LPshowansmsg").text(_("Show Answers When Closed"));
				$(".question").removeClass("LPinactive");
				working = false;
			}
		});
	}
	function stopQuestionHandler() {
		stopQuestion();
	}
	function stopQuestion(pushstate) {
		if (curquestion<0 || curstate!=2 || working) { return;}
		$("#LPstopq").text(_("Stopping Assessment..."));
		working = true;
		if (typeof pushstate != 'undefined') {
			var newstate = pushstate;
		} else if ( $("#LPshowanschkbox").is(":checked") ) {
			var newstate = 4;
		} else {
			var newstate = 3;
		}

		$.ajax({
			url: assesspostbackurl+'&action=livepollstopq&qn='+curquestion+'&newstate='+newstate,
			async: (typeof pushstate == 'undefined' || pushstate!=0),
			retryCount: 0,
			retryLimit: 61,
			error : function(xhr, textStatus, errorThrown ) {
				this.retryCount++;
				if (this.retryCount <= this.retryLimit) {
					$("#LPstopq").text(_("Stopping Assessment"+".".repeat(this.retryCount%4)));
					var ajaxObject = this;
					window.setTimeout(function(){
						$.ajax(ajaxObject);
					},Math.floor(250+Math.random()*750));
					return;
				} else{
					$("#LPstopq").text(_("Stop Assessment"));
					working = false;
				}
			},
			success: function (data) {
				$("#LPstopq").text(_("Stop Assessment")).hide();
				$("#LPstartq").show();
				if (typeof pushstate == 'undefined') {
					//skip actual closeout on pushstate
					//new showq callback will handle it.
					curstate = newstate;
					clearInterval(LPtimer);
					$("#LPshowansmsg").text(_("Show Answers"));
					showAnsIfAllowed();
					$("#LPshowrchkbox").prop("checked", settings.showresonclose || $("#LPshowrchkbox").is(":checked")).trigger("change");
					$(".sabtn").show();
					$(".question").addClass("LPinactive");
				}
				working = false;
			}
		});
	}
	function LPtimerkeeper() {
		var now = Date.now();
		if (LPtimestart==0) {
			LPtimestart = now;
		}
		var elapsed = Math.round((now - LPtimestart)/1000);
		if (settings.countdown > 0) {
			elapsed = settings.countdown - elapsed;
			if (elapsed <= 0) {
				elapsed = 0;
				if (isteacher) {
					stopQuestion();
				}
			}
		}
		var sec = elapsed%60;
		var min = (Math.floor(elapsed/60))%60;
		var hrs = Math.floor(elapsed/3600);
		var timestr = " ";
		if (hrs>0) {
			timestr += hrs+":";
		}
		if (min>9 || hrs==0) {
			timestr += min+":";
		} else if (min>0) {
			timestr += "0"+min+":";
		} else {
			timestr += "0:";
		}
		if (sec>9) {
			timestr += sec;
		} else {
			timestr += "0"+sec;
		}
		$("#livepolltopright").html(timestr);
	}

	function saving(){
		counter = counter>3?0:counter+1;
		var text = "Saving"+".".repeat(counter)
		$("#livepollsubmitmsg").html(text);
	}

	this.submitQuestion = function(qn) {
		if(window.countertimer != null){
			clearInterval(window.countertimer);
		}
		window.countertimer = setInterval(saving,1000);
		if (typeof tinyMCE != 'undefined') {tinyMCE.triggerSave();}
		doonsubmit();
		params = {
			embedpostback: true,
			toscore: qn,
			asidverify: document.getElementById("asidverify").value,
			disptime: document.getElementById("disptime").value,
			isreview: document.getElementById("isreview").value
		};
		var els = new Array();
		var tags = document.getElementsByTagName("input");
		for (var i=0;i<tags.length;i++) {
			els.push(tags[i]);
		}
		var tags = document.getElementsByTagName("select");
		for (var i=0;i<tags.length;i++) {
			els.push(tags[i]);
		}
		var tags = document.getElementsByTagName("textarea");
		for (var i=0;i<tags.length;i++) {
			els.push(tags[i]);
		}
		var regex = new RegExp("^(qn|tc)("+qn+"\\b|"+(qn+1)+"\\d{3})");
		var isFile = false;
		for (var i=0;i<els.length;i++) {
			if (els[i].name.match(regex)) {
				if ( els[i].type!='file' && ((els[i].type!='radio' && els[i].type!='checkbox') || els[i].checked)) {
					params[els[i].name] = els[i].value;
					//params += ('&'+els[i].name+'='+encodeURIComponent(els[i].value));
				} 
				if (els[i].type=='file'){
					params = new FormData();
					params.append("qn2",$("#qn2")[0].files[0]);
					params.append("toscore",qn);
					isFile = true;
				}
			}
		}
		if(isFile){
			$.ajax({
				type: "POST",
				processData: false,
				contentType: false,
				url: assesspostbackurl+'&action=livepollscoreq',
				data: params,
				retryCount: 0,
				retryLimit: 61,
				error : function(xhr, textStatus, errorThrown ) {
					this.retryCount++;
					if (this.retryCount <= this.retryLimit) {
						var ajaxObject = this;
						window.setTimeout(function(){
							$.ajax(ajaxObject);
						},Math.floor(250+Math.random()*750));
						return;
					} else{
						clearInterval(window.countertimer);
						window.countertimer = null;
						counter = 0;
						$("#livepollsubmitmsg").html(_("Error, please try again."));
					}
				},
				success: function (data) {
					data = JSON.parse(data);
					clearInterval(window.countertimer);
					window.countertimer = null;
					counter = 0;
					if (data.hasOwnProperty("error")) {
						$("#livepollsubmitmsg").html(_("Error") + ": "+data.error);
					} else {
						$("#livepollsubmitmsg").html(_("Saved"));
					}
					if(data.hasOwnProperty("file")){
						$(".toppad").contents().slice(1).remove();
						$(".toppad").append(data['file']);
					}
				}
			});
		} else {
			$.ajax({
				type: "POST",
				url: assesspostbackurl+'&action=livepollscoreq',
				data: params,
				retryCount: 0,
				retryLimit: 61,
				error : function(xhr, textStatus, errorThrown ) {
					this.retryCount++;
					if (this.retryCount <= this.retryLimit) {
						var ajaxObject = this;
						window.setTimeout(function(){
							$.ajax(ajaxObject);
						},Math.floor(250+Math.random()*750));
						return;
					} else{
						clearInterval(window.countertimer);
						window.countertimer = null;
						counter = 0;
						$("#livepollsubmitmsg").html(_("Error, please try again."));
					}
				},
				success: function (data) {
					data = JSON.parse(data);
					clearInterval(window.countertimer);
					window.countertimer = null;
					counter = 0;
					if (data.hasOwnProperty("error")) {
						$("#livepollsubmitmsg").html(_("Error") + ": "+data.error);
					} else {
						$("#livepollsubmitmsg").html(_("Saved"));
					}
				}
			});
		}

		/*
		for (var i=0;i<els.length;i++) {
			if (els[i].name.match(regex)) {
				if ( els[i].type!='file' || ((els[i].type!='radio' && els[i].type!='checkbox') || els[i].checked)) {
					params[els[i].name] = els[i].value;
					//params.append(els[i].name,els[i].value);
					//params += ('&'+els[i].name+'='+encodeURIComponent(els[i].value));
				} else if (els[i].type='file'){
					tmp = new FormData();
					tmp.append("qn2",$("#qn2")[0].files[0]);
					//params[els[i].name] = tmp;
					//params["fileupload"]=true;
				}
			}
		}

		$.ajax({
			type: "POST",
			url: assesspostbackurl+'&action=livepollscoreq',
			data: params
		}).done(function(data) {
			data = JSON.parse(data);
			if (data.hasOwnProperty("error")) {
				$("#livepollsubmitmsg").html(_("Error") + ": "+data.error);
			} else {
				$("#livepollsubmitmsg").html(_("Saved"));
			}
		});
		*/

	}
	function condenseDraw(str) {
		var la = str.replace(/\(/g,"[").replace(/\)/g,"]");
		la = la.split(";;")
		if  (la[0]!='') {
			la[0] = '['+la[0].replace(/;/g,"],[")+"]";
		}
		la = '[['+la.join('],[')+']]';
		var drawarr = JSON.parse(la);
		if (drawarr[0].length>0) {//has freehand lines
			for (var i=0;i<drawarr[0].length;i++) {
				if (drawarr[0][i].length==2) { //if line has two points, sort them
					drawarr[0][i].sort(function(a,b) {
						if (a[0]==b[0]) {
							return (a[1]-b[1]);
						} else {
							return (a[0]-b[0]);
						}
					});
				}
			}
		} else if (drawarr.length>4 && drawarr[4].length>0) {//has ineq graphs
			return str;
		}
		if (drawarr[1].length>0) {//has dots
			drawarr[1].sort(function(a,b) {
				if (a[0]==b[0]) {
					return (a[1]-b[1]);
				} else {
					return (a[0]-b[0]);
				}
			});
		}
		if (drawarr[2].length>0) {//has opendots
			drawarr[2].sort(function(a,b) {
				if (a[0]==b[0]) {
					return (a[1]-b[1]);
				} else {
					return (a[0]-b[0]);
				}
			});
		}
		var cc,newcc,m,b;
		if (drawarr.length>3 && drawarr[3].length>0) { //handle twopoint curves
			// type, x1, y1, x2, y2
			//  0    1    2   3  4
			for (var i=0;i<drawarr[3].length;i++) {
				cc = drawarr[3][i];
				if (cc[0]==5) {//standard line
					if (cc[1]==cc[3]) {
						newcc = [5,"x",cc[1]];
					} else {
						m = (cc[4]-cc[2])/(cc[3]-cc[1]);
						b = cc[2] - m*cc[1]
						newcc = [5, m.toFixed(4), b.toFixed(2)];
					}
					drawarr[3][i] = newcc;
				} else if (cc[0]==5.2) {//ray
					if (cc[1]==cc[3]) {
						newcc = [5.2,"x",cc[1],cc[2]];
					} else {
						m = (cc[4]-cc[2])/(cc[3]-cc[1]);
						newcc = [5.2, m.toFixed(4), cc[1],cc[2]];
					}
					drawarr[3][i] = newcc;
				} else if (cc[0]==5.3) {//line seg
					if (cc[1]<cc[3] || (cc[1]==c[3] && cc[2]<cc[4])) {
						newcc = [5.3, cc[1],cc[2],cc[3],cc[4]];
					} else {
						newcc = [5.3, cc[3],cc[4],cc[1],cc[2]];
					}
					drawarr[3][i] = newcc;
				} else if (cc[0]==6) {//parab
					if (cc[1]==cc[3]) {
						newcc = [6,"x",cc[1],cc[2]];
					} else {
						m = (cc[4]-cc[2])/((cc[3]-cc[1])*(cc[3]-cc[1]));
						newcc = [6, m.toFixed(4), cc[1], cc[2]];
					}
					drawarr[3][i] = newcc;
				} else if (cc[0]==6.5) {//sqrt
					if (cc[1]==cc[3]) {
						newcc = [6.5,"x",cc[1],cc[2]];
					} else {
						b = (cc[3]>cc[1])?1:-1;
						m = (cc[4]-cc[2])/Math.sqrt(Math.abs(cc[3]-cc[1]));
						newcc = [6.5, m.toFixed(4), b, cc[1], cc[2]];
					}
					drawarr[3][i] = newcc;
				} else if (cc[0]==8) {//abs
					if (cc[1]==cc[3]) {
						newcc = [8,"x",cc[1],cc[2]];
					} else {
						m = (cc[4]-cc[2])/Math.abs(cc[3]-cc[1]);
						newcc = [8, m.toFixed(4), cc[1], cc[2]];
					}
					drawarr[3][i] = newcc;
				}
			}
		}
		return JSON.stringify(drawarr);
	}
	var PI2 = Math.PI * 2;
	function drawX(ctx, x, y) {
		ctx.beginPath();
		ctx.strokeStyle = 'white';
		ctx.moveTo(x - 10, y - 10);
		ctx.lineTo(x + 10, y + 10);
		ctx.lineWidth = 4;
		ctx.stroke();
	
		ctx.moveTo(x + 10, y - 10);
		ctx.lineTo(x - 10, y + 10);
		ctx.stroke();

		ctx.beginPath();
		ctx.strokeStyle = 'black';
		ctx.moveTo(x - 10, y - 10);
		ctx.lineTo(x + 10, y + 10);
		ctx.lineWidth = 2;
		ctx.stroke();
	
		ctx.moveTo(x + 10, y - 10);
		ctx.lineTo(x - 10, y + 10);
		ctx.stroke();
		ctx.strokeStyle = 'white';

		ctx.beginPath();
		ctx.arc(x, y, 3, 0, PI2);
		ctx.closePath();
		ctx.fillStyle = 'red';
		ctx.fill();
	}
};

if (!String.prototype.repeat) {
	String.prototype.repeat = function(count) {
	  'use strict';
	  if (this == null)
		throw new TypeError('can\'t convert ' + this + ' to object');
  
	  var str = '' + this;
	  // To convert string to integer.
	  count = +count;
	  // Check NaN
	  if (count != count)
		count = 0;
  
	  if (count < 0)
		throw new RangeError('repeat count must be non-negative');
  
	  if (count == Infinity)
		throw new RangeError('repeat count must be less than infinity');
  
	  count = Math.floor(count);
	  if (str.length == 0 || count == 0)
		return '';
  
	  // Ensuring count is a 31-bit integer allows us to heavily optimize the
	  // main part. But anyway, most current (August 2014) browsers can't handle
	  // strings 1 << 28 chars or longer, so:
	  if (str.length * count >= 1 << 28)
		throw new RangeError('repeat count must not overflow maximum string size');
  
	  var maxCount = str.length * count;
	  count = Math.floor(Math.log(count) / Math.log(2));
	  while (count) {
		 str += str;
		 count--;
	  }
	  str += str.substring(0, maxCount - str.length);
	  return str;
	}
  }