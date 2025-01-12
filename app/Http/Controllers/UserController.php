<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Follow;
use App\Models\User;
use App\Models\Friend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    private $apiResponse;

    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->apiResponse->UnAuthorization();
        }
        $user = User::with('follower', 'follows')
            ->select(
                'id',
                'name',
                'email',
                'avatar',
                'overview',
            )->where('id', $userId)->first();
        $user->followers = count($user->follower);
        $user->following = count($user->follows);
        unset($user->follower, $user->follows);
        $folderAvatar = null;
        if (!is_null($user->avatar)) {
            $folderAvatar = explode('@', $user->email);
            $user->avatar = url(
                'avatars/' . $folderAvatar[0] . '/' . $user->avatar
            );
        }
        return $this->apiResponse->success($user);
    }

    /**
     * Suggest friend
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */

    public function suggestFriend(Request $request)
    {
        $userId = Auth::id();
        $listFriend = DB::table('friends')
            ->where('user_id', $userId)
            ->select('friend_id')
            ->pluck('friend_id')->toArray();
        $listFriend[] = $userId;
        $suggests = User::with([
            'experiences' => function ($experiencesQuery) {
                return $experiencesQuery->select('id', 'user_id', 'title');
            }
        ])->whereNotIn('id', $listFriend)
            ->where('status', User::STATUS_ACTIVE)
            ->select('id', 'name', 'email', 'avatar')
            ->limit(config('const.limit'))
            ->get();
        if (count($suggests) > 0) {
            foreach ($suggests as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->experience = $this->truncateString($txtExperience, 20);
                unset($user->experiences);
            }
        }
        return $this->apiResponse->success($suggests);
    }
    private function truncateString($string, $length, $append = '...')
    {
        if (mb_strlen($string) > $length) {
            return mb_substr($string, 0, $length) . $append;
        }
        return $string;
    }

    public function listFriendRequest()
    {
        $userId = Auth::id();
        //List request friend
        $requests = User::with([
            'experiences' => function ($experiencesQuery) {
                return $experiencesQuery->select('id', 'user_id', 'title');
            }
        ])->join(
            'friends',
            'users.id',
            'friends.user_id'
        )->where('friends.friend_id', $userId)
            ->where('users.status', User::STATUS_ACTIVE)
            ->where('friends.approved', Friend::UN_APPROVED)
            ->select('friends.id', 'users.name', 'users.email', 'users.avatar', 'users.created_at')
            ->orderBy('friends.created_at', 'asc')
            ->limit(config('const.limit'))
            ->get();
        if (count($requests) > 0) {
            foreach ($requests as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->experience = $this->truncateString($txtExperience, 15);
                $user->name = $this->truncateString($user->name, 10);
                unset($user->experiences);
            }
        }
        return $this->apiResponse->success($requests);
    }

    /**
     * Add friend
     * @param Request $request
     * @return \Illuminate\Http\Response
     */

    public function addFriend(Request  $request)
    {
        $params = $request->all();
        try {
            DB::beginTransaction();
            $friend = new Friend();
            $friend->user_id = Auth::id();
            $friend->friend_id = $params['friend_id'];
            $friend->approved = Friend::UN_APPROVED;
            $friend->created_at = Carbon::now();
            $friend->save();
            DB::commit();
            return $this->apiResponse->success();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->apiResponse->InternalServerError();
        }
    }

    public function accept(Request $request)
    {
        $param = $request->all();
        if ($param['type'] == 'accept') {
            //Appove
            return DB::table('friends')
                ->where('id', $param['id'])
                ->update([
                    'approved' => Friend::APPROVED
                ]);
        } else {
            //Removes
            return DB::table('friends')
                ->where('id', $param['id'])
                ->delete();
        }
    }

    public function mostFollowed(Request $request)
    {
        $user = User::select(
            'users.id',
            'users.name',
            'users.email',
            'users.avatar',
            DB::raw('COUNT(follows.id) as total_follow'),
            'follows.follow_id'
        )->join('follows', 'users.id', 'follows.follow_id')
            ->with([
                'experiences' => function ($experienceQuery) {
                    return $experienceQuery->select('id', 'user_id', 'title');
                }
            ])->groupBy(
                'follows.follow_id',
                'users.id',
                'users.name',
                'users.email',
                'users.avatar'
            )->orderBy('total_follow', 'DESC')->first();
        $folderAvatar = null;
        if (!is_null($user->avatar)) {
            $folderAvatar = explode('@', $user->email);
            $user->avatar = url(
                'avatars/' . $folderAvatar[0] . '/' . $user->avatar
            );
        }
        $txtExperience = '';
        $i = 1;
        foreach ($user->experiences as $experience) {
            if ($i < count($user->experiences)) {
                $txtExperience .= $experience->title . ', ';
            } else {
                $txtExperience .= $experience->title;
            }
            $i++;
        }
        $user->experience = $this->truncateString($txtExperience, 15);
        $user->name = $this->truncateString($user->name, 10);
        unset($user->experiences);
        return $this->apiResponse->success($user);
    }

    public function search(Request $request)
    {
        $param = $request->all();
        $users = User::with([
            'experiences' => function ($experienceQuery) {
                return $experienceQuery->select('id', 'user_id', 'title');
            }
        ])->select(
            'id', 'name', 'email', 'avatar'
        )->whereHas('experiences', function ($query) use ($param) {
            return $query->where('title', 'like', '%' . $param['key-word'] . '%');
        })->orWhere('name', 'like', '%' . $param['key-word'] . '%')
            ->orWhere('email', 'like', '%' . $param['key-word'] . '%')
            ->orderBy('id', 'DESC')
            ->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->name = $this->truncateString($user->name, 100);
                $user->experience = $this->truncateString($txtExperience, 100);
                unset($user->experiences);   
            }
        }
        return $this->apiResponse->success($users);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
