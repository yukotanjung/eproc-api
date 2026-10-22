@php
    use Carbon\Carbon;
    $exportDate = Carbon::now('UTC');
    $exportDate = $exportDate->setTimezone($timezone)->format('d F Y H:i:s');

    $showCampaign = false;
    $showDevice = false;
    $showContent = false;
    $showPlaylist = false;

    if(in_array("campaign_id", $groups)) $showCampaign = true;
    if(in_array("device_id", $groups)) $showDevice = true;
    if(in_array("content_id", $groups)) $showContent = true;
    if(in_array("campaign_playlist_id", $groups)) $showPlaylist = true;

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="{{ resource_path('scss/mpdf.scss') }}" rel="stylesheet">
</head>

<body>
    <div class='header'>
        <h3 class='text' style="text-align: center">Report Campaign {{ $exportDate }}</h3>
    </div>
    <div class='row'>
        <table class="table" style='margin-top:1rem;width:100%;'>
            <thead>
                <tr style="background-color:#9c9b9b;">
                    <th>No</th>
                    <th>Date</th>
                    @if ($showCampaign)
                        <th>Campaign</th>
                    @endif
                    @if ($showDevice)
                        <th>Device</th>
                    @endif
                    @if ($showContent)
                        <th>Media</th>
                    @endif
                    @if ($showPlaylist)
                        <th>Playlist</th>
                    @endif
                    <th>Play Count</th>
                    <th>Play Duration</th>
                </tr>;
            </thead>
            <tbody>
                @php
                    $no = 1;
                @endphp
                @foreach ($result as $item)
                <tr>
                    <td>{{$no}}</td>
                    <td>
                        @php
                            $stringFormat = 'd F Y H:i';
                            if($interval === "daily") $stringFormat = 'd F Y';
                            elseif($interval === "monthly") $stringFormat = 'F Y';
                            $inputDate = Carbon::parse($item->input_date)->setTimezone('UTC');
                            $inputDate = $inputDate->setTimezone($timezone)->format($stringFormat);
                        @endphp
                        {{$inputDate}}
                    </td>
                    @if ($showCampaign)
                        <td>{{!empty($item->campaign_id) ? $item->campaign->name : '-'}}</td>
                    @endif
                    @if ($showDevice)
                        <td>{{!empty($item->device_id) ? $item->device->name : '-'}}</td>
                    @endif
                    @if ($showContent)
                        <td>{{!empty($item->content_id) ? $item->content->name : '-'}}</td>
                    @endif
                    @if ($showPlaylist)
                        <td>{{!empty($item->campaign_playlist_id) ? $item->playlist->name : '-'}}</td>
                    @endif
                    <td>{{$item->play_count}}</td>
                    <td>{{gmdate("H:i:s",$item->play_time)}}</td>

                </tr>
                    @php
                        $no++;
                    @endphp
                @endforeach
            </tbody>
        </table>
    </div>

</body>

</html>
