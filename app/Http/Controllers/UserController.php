<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Mail\VerifyEmail;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\ResetPassword;
use App\Models\Posts;

class UserController extends Controller
{

    public function index()
    {

        return view('Users');
    }

    public function home(){


        // $posts = Posts::;


        $posts = Posts::with('user')->orderBy('id', 'DESC')->paginate(2);



        // // $name = $data->name;
        // dd($data[0]->user->name);


        return view('welcome',compact(['posts']))->with('no',1);
    }


    public function store(UserRequest $request)
    {
        $user_input = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ];


        $data = User::create($user_input);
        if ($data) {
            $verify2 = DB::table('users')->where([
                ['email', $request->all()['email']]
            ]);

            if ($verify2->exists()) {
                $verify2->delete();
            }

            $pin = rand(100000, 999999);
            DB::table('users')->insert([
                'name' => $request->name,
                'email' => $request->all()['email'],
                'password' => Hash::make($request->password),
                'remember_token' => $pin
            ]);
        }
        // dd($pin);


        Mail::to($request->email)->send(new VerifyEmail($pin));
        // dd($data);
        $token = $data->createToken('myapptoken')->plainTextToken;

        return new JsonResource(
            [
                'success' => true,
                'message' => 'Successful created user. Please check your email for a 6-digit pin to verify your email.',
                'remember_token' => $token
            ],
            201
        );



        // return redirect('login');
    }

    public function pinindex()
    {
        return view('pin-verification');
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'remember_token' => ['required'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with(['message' => $validator->errors()]);
        }
        // return User::where('remember_token',$request->remember_token)->get();
        $select = User::where('remember_token', $request->remember_token)->get();
        // return $select->count();
        if ($select->count() == 0) {
            return new JsonResponse(['success' => false, 'message' => "Invalid PIN"], 400);
        }


        $check = User::updateOrCreate(
            ['remember_token' => $request->remember_token],
            ['remember_token' => 'Verified'],
        );

        // return $check;

        // return $user = User::where('id',$request->id)->get();
        // $user->  = Carbon::now()->getTimestamp();
        $check->save();

        return view('login');
    }



    public function forgotpassword()
    {
        return view('forgot-password');
    }


    public function forgotpasswordvalidation()
    {
        $user = User::where('remember_token', $pin)->first();
        if ($user) {
            $email = $user->email;
            return view('forgot-password', compact('email'));
        }
        return redirect()->route('forgot-password')->with('failed', 'Password reset link is expired');
    }

    public function resetPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->with('failed', 'Failed! email is not registered.');
        }

        $token = Str::random(60);

        $user['remember_token'] = $token;

        $user->save();

        Mail::to($request->email)->send(new ResetPassword($token));

        if (Mail::failures() != 0) {
            return back()->with('success', 'Success! password reset link has been sent to your email');
        }
        return back()->with('failed', 'Failed! there is some issue with email provider');
    }

    public function changepassword($token)
    {
        return view('change-password', ['token' => $token]);
    }

    public function updatePassword(Request $request)
    {
        $this->validate($request, [
            'remember_token' => 'required',
            'password' => 'required|min:6',
            'confirm_password' => 'required|same:password'
        ]);
        // return $request->all();

        $user = User::where('remember_token', $request->remember_token)->get();
        // return $user;
        $id =  $user[0]->id;
        if ($user) {
            // $user['is_verified'] = 0;

            $newuser = User::find($id);
            // return $newuser;


            $newuser['remember_token'] = '';
            $newuser['password'] = Hash::make($request->password);
            $newuser->save();
            // return $newuser;
            return redirect('/login')->with('success', 'Success! password has been changed');
        }
        return redirect()->route('forgot-password')->with('failed', 'Failed! something went wrong');
    }



    public function logout()
    {

        Auth::logout();
        return redirect('login');
    }

    public function build()
    {
        return $this
            ->subject("Email Verification")
            ->markdown('emails.verify');
    }
}
