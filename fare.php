<?php
// 取得行事曆資料
$jsonData = [];
$calendarPath = sprintf('%s/data/', __DIR__);
$calendarFileList = array_diff(scandir($calendarPath), ['..', '.']);
if (!empty($calendarFileList)) {
    foreach ($calendarFileList as $calendarFile) {
        $tempData = json_decode(file_get_contents($calendarPath . $calendarFile));
        if (is_array($tempData)) {
            foreach ($tempData as $i => $temp) {
                $jsonData[$temp->西元日期] = [
                    'date' => $temp->西元日期,
                    'isHoliday' => '2' === $temp->是否放假 ? 1 : 0,
                    'name' => $temp->備註,
                    'weekday' => $temp->星期,
                ];
            }
        }
    }
}
// print_r($jsonData);exit;
$jsonData = json_encode($jsonData);

$title = '通勤票價日期計算';
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
  <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.zh-TW.min.js" charset="UTF-8"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/locale/zh-tw.js"></script>
</head>
<body>
<div class="container">
  <h3><?php echo $title; ?></h3>
  <form id="dateForm" method="post" role="form">
    <div class="form-group">
      <label>起站</label>
      <select class="form-control station" name="station_start" required="required">
        <option value="">請選擇</option>
      </select>
    </div>
    <div class="form-group">
      <label>訖站</label>
      <select class="form-control station" name="station_end" required="required">
        <option value="">請選擇</option>
      </select>
    </div>
    <div class="form-group">
      <label for="endDate">定期票天數</label>
      <select class="form-control" name="diff_day" required="required">
        <option value="30">30 天</option>
        <option value="60" selected="selected">60 天</option>
      </select>
    </div>
    <div class="form-group">
      <label for="endDate">假日誤差天數</label>
      <input type="number" class="form-control" name="not_work_day" placeholder="請輸入假日誤差天數" />
    </div>
    <div class="form-group">
      <label for="startDate">起日</label>
      <input type="text" class="form-control date" id="startDate" name="start_date" placeholder="請輸入起日" required="required" value="" readonly="" style="background-color: #fff" />
    </div>
    <div class="form-group">
      <label for="endDate">迄日</label>
      <input type="text" class="form-control date" id="endDate" name="end_date" placeholder="請輸入迄日" required="required" value="" readonly="" style="background-color: #fff" />
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block">計算</button>
  </form>
  <div id="json-data-holiday" style="display: none; "><?php echo $jsonData; ?></div>
  <div class="highlight" style="height: 300px; ">
    <blockquote class="resultBlock">
      <p>總天數 <code id="totalDay"></code></p>
      <p>工作天數 <code id="workDay"></code></p>
      <p>假日 <code id="holiday"></code></p>
      <p>票價 <code id="fare"></code></p>
      <p>平均單日票價 <code id="fareForDay"></code></p>
      <p>折數 <code id="discount"></code></p>
      <p>結果 <code id="result"></code></p>
    </blockquote>
  </div>
</div>

<script type="text/javascript">
// 預設值
const defailtValue = {
  // 埔心
  station_start: '1018',
  // 台北
  station_end: '1008',
};

// API url
const apiUrl = {
  // 車站列表
  station: '//ptx.transportdata.tw/MOTC/v2/Rail/TRA/Station',
  // 車站票價
  fare: '//ptx.transportdata.tw/MOTC/v2/Rail/TRA/ODFare'
};

// 定期票計算公式
const regularTicketFormula = {
  30: {
    'days': 30,
    'workdays': 21,
    'discount': 0.85,
  },
  60: {
    'days': 60,
    'workdays': 42,
    'discount': 0.8,
  },
};

// 悠遊卡基準折數
const discountEasycard = 0.9;

// 自動計算結束日
function calculateDate($startDate = $('input[name="start_date"]'), $endDate = $('input[name="end_date"]'), isEnd = true) {
  let inputDate = moment();
  let $inputDate = (isEnd ? $startDate : $endDate);
  let $changeDate = (isEnd ? $endDate : $startDate);
  if (0 < $inputDate.val().length) {
    inputDate = moment($inputDate.val());
  } else {
    $inputDate.val(inputDate.format('YYYY-MM-DD'));
  }
  let changeDate;
  const diffDays = $('select[name="diff_day"]').val() - 1;
  if (isEnd) {
    changeDate = inputDate.add(diffDays, 'days');
  } else {
    changeDate = inputDate.subtract(diffDays, 'days');
  }
  $changeDate.val(changeDate.format('YYYY-MM-DD')).datepicker('update');
}

// 重新整理車站列表
var stationPair = {};
function reloadStationList() {
  $.get(apiUrl.station, {
    '$format': 'json'
  }, function(stations) {
    if (0 === stations.length) {
      return false;
    }
    for (let i in stations) {
      stationPair[stations[i]['StationID']] = stations[i]['StationName']['Zh_tw'];
    }

    const stationNameList = ['station_start', 'station_end'];
    for (let i in stationNameList) {
      let $station = $('select[name=' + stationNameList[i] + ']');
      $station.find('option').not(':first').remove();
      for (let j in stationPair) {
        let $option = $('<option/>').val(j).text('undefined' !== typeof stationPair[j] ? stationPair[j] : '-');
        if (j === defailtValue[stationNameList[i]]) {
          $option.prop('selected', true);
        }
        $station.append($option);
      }
    }
  }, 'json');
}

// 取得票價計算結果
function getFareResult(stationIdStart = '', stationIdEnd = '', days = 0) {
  if (0 === stationIdStart.length || 0 === stationIdEnd.length || 0 === days) {
    alert('資料設定錯誤，請重新輸入');
    return false;
  }

  // reset holidayInfoList
  holidayInfoList = [], makeUpWorkdayInfoList = [];

  $.ajax({
    'url': `${apiUrl.fare}/${stationIdStart}/to/${stationIdEnd}`,
    'data': {'$format': 'json'},
    'dataType': 'json',
    'success': function(result) {
      if (0 === result[0].length || 0 === result[0].Fares.length) {
        alert('資料取得錯誤，請稍後重試');
        return false;
      }
      const json = result[0];
      for (let i in json.Fares) {
        if ('成復' === json.Fares[i]['TicketType']) {
          $('.resultBlock').fadeIn();

          // total
          const price = json.Fares[i]['Price'];
          const regularTicketInfo = regularTicketFormula[days];
          const total = Math.round(price * regularTicketInfo['workdays'] * 2 * regularTicketInfo['discount']);
          $('.resultBlock p code#fare').text(total);

          // 工作天
          let workingDays = getWorkingDays($('input[name="start_date"]').val(), $('input[name="end_date"]').val());

          console.log(holidayInfoList);
          console.log(makeUpWorkdayInfoList);

          if ('' !== $('input[name=not_work_day]').val()) {
            workingDays -= parseInt($('input[name=not_work_day]').val());
          }
          const fareForDay = Math.ceil(total / workingDays);
          const discount = Math.ceil(fareForDay / (price * 2) * 100) / 100;
          $('.resultBlock p code#workDay').text(workingDays);
          $('.resultBlock p code#totalDay').text(regularTicketInfo['days']);
          $('.resultBlock p code#holiday').html((0 < holidayInfoList.length ? '<br />' + holidayInfoList.join('<br />') : '無') + (0 < makeUpWorkdayInfoList.length ? '<br /><span style="font-weight: 900; color: #f9f2f4; background-color: #c7254e; ">' + makeUpWorkdayInfoList.join('<br />') + '</span>' : ''));
          $('.resultBlock p code#fareForDay').text(fareForDay);
          $('.resultBlock p code#discount').text(discount);
          $('.resultBlock p code#result').html(discount >= discountEasycard ? '<sapn style="font-color: red; font-weight: 900; ">悠遊卡</span>' : '定期票');
          break;
        }
      }

    }
  });
  return false;
}

// 取得工作日
function getWorkingDays(startDate = '', endDate = '') {
  let momentStartDate = moment(startDate);
  let momentEndDate = moment(endDate);
  let currentDate = momentStartDate;
  let workingDays = 0;
  while (currentDate <= momentEndDate) {
    if (!isHoliday(currentDate)) {
      workingDays++;
    }
    currentDate.add(1, 'days');
  }
  return workingDays;
}

// 是否為假日
const holidayData = $.parseJSON($('#json-data-holiday').text());
let holidayInfoList = [], makeUpWorkdayInfoList = [];
function isHoliday(date = '') {
  let isHoliday = false;
  if ('' === date) {
    return isHoliday;
  }
  date = moment(date);
  const keyDate = date.format('YYYYMMDD');

  if ("object" !== typeof holidayData[keyDate]) {
      return isHoliday;
  } else {
      isHoliday = (1 === holidayData[keyDate]['isHoliday']);
      if (0 < holidayData[keyDate]['name'].length) {
          var dateInfo = date.format('YYYY/M/D') + ': ' + holidayData[keyDate]['name'];
          if (isHoliday) {
              holidayInfoList.push(dateInfo);
          } else {
              makeUpWorkdayInfoList.push(dateInfo);
          }
      }
  }
  return isHoliday;
}

$(function() {
  // 重整車站列表
  reloadStationList();

  // 隱藏結果
  $('.resultBlock').hide();

  // 日曆
  $('.date').datepicker({
    format: 'yyyy-mm-dd',
    todayHighlight: true,
    language: 'zh-TW',
    weekStart: 0,
    immediateUpdates: true,
    autoclose: true
  });
  let $startDate = $('input[name="start_date"]');
  let $endDate = $('input[name="end_date"]');
  $startDate.on('changeDate', function() {
    calculateDate($startDate, $endDate);
  });

  // 預設今天
  if ("" === $startDate.val()) {
      $startDate.val(moment().format('YYYY-MM-DD'));
      calculateDate($startDate, $endDate);
  }

  // 切換天數時重新計算結束日
  $('select[name="diff_day"]').change(function() {
      calculateDate($startDate, $endDate);
  });

  // 計算
  $('form').submit(function() {
    getFareResult($('select[name=station_start]').val(), $('select[name=station_end]').val(), $('select[name=diff_day]').val());
    return false;
  });

});
</script>
</body>
</html>