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
        <style>
        .well .btn {
            margin: 6px 0;
            width: calc((100% - 20px) / 3);
            border: 2px solid #5bc3e8;
            background-color: #fff;
            border-radius: 10px;
            color: #5bc3e8;
            font-size: 14px;
            height: 50px;
            font-weight: 900;
            padding: 0;
        }
        .well .btn:not(:nth-of-type(3n)) {
            margin-right: 10px;
        }
        </style>


        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/localforage/1.5.3/localforage.min.js"></script>
    </head>
<body>
        <div class="container">
            <h3><?php echo $title; ?></h3>
            <form id="dateForm" method="post" role="form">
                <div class="form-group">
                    <label>車站</label>
                    <select class="form-control" name="station" required="required">
                        <option value="">請選擇</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>方向</label>
                    <select class="form-control" name="direction" required="required">
                        <option value="">請選擇</option>
                        <option value="0">北上</option>
                        <option value="1">南下</option>
                    </select>
                </div>
                <div class="form-group history">
                    <label>歷史紀錄</label>
                    <div class="well" style="padding: 5px 10px; ">
                      <button class="btn" type="button" style="margin: 6px 0; width: calc((100% - 20px) / 3); border: 2px solid #5bc3e8; background-color: #fff; border-radius: 10px; color: #5bc3e8; font-size: 14px; height: 50px; font-weight: 900; padding: 0; ">台北 北上</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">查詢</button>
            </form>
            <div class="highlight resultBlock"></div>
        </div>

        <script type="text/javascript">
            // 預設值（上午預設埔心，其他時段預設台北）
            const hour = new Date().getHours();
            let defaultValue = {
              // 車站
              station: ((hour >= 3 && hour <= 12) ? '1018' : '1008'),
              // 方向
              direction: (hour >= 3 && hour <= 12) ? '0' : '1'
            };

            // localforage config
            const historyKey = "history";
            localforage.config({
              dirver: "indexedDB",
              name: "danielhuang-030/commuting",
              version: 3,
              description: "danielhuang-030/commuting"
            });

//            localforage.getItem(historyKey).then(function(value) {
//              if (null !== value) {
//                console.log(value);
//              }
//            }).catch (function(err) {
//              console.log("getItem");
//              console.log(err);
//            });

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
                  if (i === defaultValue['station']) {
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

            // reload history
            function reloadHistory() {
              const $history = $("div.history");
              console.log(stationPair);

              localforage.getItem(historyKey).then(function(value) {
                if (0 === value.length) {
                  $history.hide();
                } else {
                  let historyHtml = "";
                  rvalue = value.reverse();
                  $.each(rvalue, function(i, data) {
                    historyHtml += '<button class="btn" type="button">台北 北上</button>';
                  });
                  $history.fadeIn().find("div.well").html(historyHtml);
                }
              }).catch (function(err) {
                console.log(err);
              });

            }

            // 取得票價計算結果
            function getTimetableResult(stationId = defaultValue['station'], direction = defaultValue['direction']) {
              if (0 === stationId.length || 0 === direction.length) {
                alert('資料設定錯誤，請重新輸入');
                return;
              }

              // localforage save
              addHistory(stationId, direction);

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

                  // reload history
                  reloadHistory();
                }
              });
            }

            // 加入歷史紀錄
            function addHistory(stationId, direction) {
              localforage.getItem(historyKey).then(function(value) {
                if (null === value) {
                  value = [];
                } else {
                  let unsetIndexs = [];
                  $.each(value, function(i, data) {
                    if (stationId === data.stationId && direction === data.direction) {
                      unsetIndexs.push(i);
                    }
                  });
                  if (0 < unsetIndexs.length) {
                    for (let i in unsetIndexs) {
                      value.splice(unsetIndexs[i], 1);
                    }
                  }
                }
                if (5 < value.length) {
                  value.shift();
                }
                value.push({
                  stationId: stationId,
                  direction: direction,
                  date: new Date()
                });
                localforage.setItem(historyKey, value).then(function(value) {
                  console.log("setItem success");
                  // console.log(value);
                }).catch(function(err) {
                  console.log("setItem");
                  console.log(err);
                });
              }).catch (function(err) {
                console.log("getItem");
                console.log(err);
              });
            }

            $(function () {
              // 重整車站列表
              reloadStationList();

              // 重整車種列表
              reloadTrainClassificationList();

              // reload history
              reloadHistory();

              // 方向預設值
              $(`select[name=direction] option[value=${defaultValue['direction']}]`).prop('selected', true);

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