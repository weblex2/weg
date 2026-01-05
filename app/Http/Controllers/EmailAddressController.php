<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailAddress;

class EmailAddressController extends Controller
{
    public function index()
    {
        return EmailAddress::all();
    }

    public function store(Request $request)
    {
        $request->validate(['email' => 'required|email|unique:email_addresses,email']);
        EmailAddress::create(['email' => $request->email]);
        return response()->json(['success' => true]);
    }

    public function destroy(EmailAddress $email)
    {
        $email->delete();
        return response()->json(['success' => true]);
    }
}
