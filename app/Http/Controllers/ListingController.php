<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $query = Listing::with(['category', 'seller'])
            ->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $listings = $query->latest()->paginate(12);
        $categories = Category::all();


        return view('listings.index', compact('listings', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'condition' => 'required|in:new,like_new,good,fair',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->store('listings', 'public');
            }
        }

        $listing = Auth::user()->listings()->create([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'condition' => $validated['condition'],
            'images' => $imagePaths,
            'status' => 'active',
        ]);

        return redirect()->route('listings.show', $listing->id)
            ->with('success', 'Listing created successfully!');
    }

    public function show(Listing $listing)
    {
        $listing->load(['seller', 'category']);
        return view('listings.show', compact('listing'));
    }

}
