<?php
$title = '即時火車時刻表';

?>

<!DOCTYPE html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $title; ?></title>
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" />
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.css" />
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    </head>
<body>
        <div class="container">
            <h3><?php echo $title; ?></h3>
            <form id="dateForm" method="post" role="form">
                <div class="form-group">
                    <label>車站</label>
                    <select class="form-control station" name="station" required="required">
                        <option value="">請選擇</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>方向</label>
                    <select class="form-control station" name="direction" required="required">
                        <option value="">請選擇</option>
                        <option value="0">北上</option>
                        <option value="1">南下</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">查詢</button>
            </form>
            <div class="highlight resultBlock"></div>
        </div>

        <script type="text/javascript">
            // 預設值（上午預設埔心，其他時段預設台北）
            let hour = new Date().getHours();
            const defailtValue = {
              // 車站
              'station': ((hour >= 3 && hour <= 12) ? '1018' : '1008'),
              // 方向
              'direction': (hour >= 3 && hour <= 12) ? '0' : '1'
            };

            // API url
            const apiUrl = {
              // 車站列表
              'station': 'http://ptx.transportdata.tw/MOTC/v2/Rail/TRA/Station',
              // 車種列表
              'classification': 'http://ptx.transportdata.tw/MOTC/v2/Rail/TRA/TrainClassification',
              // 車次即時訊息
              'timetable': 'http://ptx.transportdata.tw/MOTC/v2/Rail/TRA/LiveBoard'
            };

            // 重新整理車站列表
            var stationPair = {};
            function reloadStationList() {
              $.get(apiUrl['station'], {
                '$format': 'json'
              }, function (stations) {
                if (0 === stations.length) {
                  return;
                }
                for (let i in stations) {
                  stationPair[stations[i]['StationID']] = stations[i]['StationName']['Zh_tw'];
                }

                let $station = $('select[name=station]');
                $station.find('option').not(':first').remove();
                for (let i in stationPair) {
                  let $option = $('<option/>').val(i).text('undefined' !== typeof stationPair[i] ? stationPair[i] : '-');
                  if (i === defailtValue['station']) {
                    $option.prop('selected', true);
                  }
                  $station.append($option);
                }
              }, 'json');
            }

            // 取得車種列表
            var trainClassificationPair = {};
            function reloadTrainClassificationList() {
              $.get(apiUrl['classification'], {
                '$format': 'json'
              }, function (classifications) {
                for (let j in classifications) {
                  if (0 === classifications.length) {
                    return;
                  }
                  for (let i in classifications) {
                    trainClassificationPair[classifications[i]['TrainClassificationID']] = classifications[i]['TrainClassificationName']['Zh_tw'];
                  }
                }
              });
            }

            // 取得票價計算結果
            function getTimetableResult(stationId = defailtValue['station'], direction = defailtValue['direction']) {
              if (0 === stationId.length || 0 === direction.length) {
                alert('資料設定錯誤，請重新輸入');
                return;
              }

              // 設定過濾條件
              let conditions = [
                `StationID eq '${stationId}'`,
                `Direction eq '${direction}'`
              ];

              $.ajax({
                'url': apiUrl['timetable'],
                'async': false,
                'data': {
                  '$filter': conditions.join(' and '),
                  '$format': 'json'
                },
                'dataType': 'json',
                'success': function (result) {
                  if (0 === result[0].length) {
                    alert('資料取得錯誤，請稍後重試');
                    return;
                  }
                  let resultHtml = ''
                  for (let i in result) {
                    resultHtml += `
                <blockquote>
                  <p>狀態 <code>${0 === result[i]['DelayTime'] ? '準點' : '<sapn style="font-color: red; font-weight: 900; ">誤點 ' + result[i]['DelayTime'] + ' 分</span>'}</code></p>
                  <p>車種 <code>${'undefined' !== typeof trainClassificationPair[result[i]['TrainClassificationID']] ? trainClassificationPair[result[i]['TrainClassificationID']] : '-'}</code></p>
                  <p>開車時間 <code>${result[i]['ScheduledDepartureTime']}</code></p>
                  <p>終點站 <code>${result[i]['EndingStationName']['Zh_tw']}</code></p>
                  <p>車次 <code>${result[i]['TrainNo']}</code></p>
                </blockquote>`;
                  }
                  if (0 < resultHtml.length) {
                    $('.resultBlock').fadeIn().html(resultHtml);
                  }
                }
              });
            }

            $(function () {
              // 重整車站列表
              reloadStationList();

              // 重整車種列表
              reloadTrainClassificationList();

              // 方向預設值
              $(`select[name=direction] option[value=${defailtValue['direction']}]`).prop('selected', true);

              // 隱藏結果
              $('.resultBlock').hide();

              // 查詢
              $('form').submit(function () {
                getTimetableResult($('select[name=station]').val(), $('select[name=direction]').val());
                return false;
              });

            });
        </script>
    </body>
</html>