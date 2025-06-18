<?php

namespace App\Http\Controllers;

use App\Exceptions\TokenException;
use App\Helpers\General;
use App\Helpers\Lists;
use App\Helpers\Token;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the channels.
     */
    public function index() : View
    {
        $accounts = Account::all();
        return view('accounts.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new account.
     */
    public function create() : View
    {
        return view('accounts.create');
    }

    /**
     * Show the form for editing the account.
     */
    public function edit(Account $account) : View
    {
        $folder = General::codeFromString($account->username, $account);
        return view('accounts.edit', compact('account', 'folder'));
    }

    /**
     * Store a new account.
     */
    public function store(Request $request) : JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:255|unique:accounts',
            'password' => 'required',
        ]);

        $data['password'] = Crypt::encryptString($data['password']);

        $account = Account::create($data);

        flashSuccessMessage('Cuenta creada correctamente.');
        return jsonIframeRedirection(route('accounts.edit', $account->id));
    }

    /**
     * Generate a new token for the account.
     */
    public function generateToken(Account $account) : JsonResponse
    {
        try {
            Token::refreshCdnToken($account);
            Lists::generate($account);
        } catch (TokenException $e) {
            Log::error($e);
            flashDangerMessage($e->getMessage());
            return jsonIframeRedirection("");
        }

        flashSuccessMessage('CDN Token obtenido y reemplazado correctamente en los archivos.');
        Log::info('CDN Token obtenido y reemplazado correctamente en los archivos.');
        return jsonIframeRedirection("");
    }
}
