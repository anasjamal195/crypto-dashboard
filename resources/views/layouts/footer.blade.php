<footer class="footer">
    <div class="container-fluid"
        style="
    display: flex;
    align-items: center;
    justify-content: space-between;
">

        @php
            $gitInfo = \App\CommonHelpers::getLastGitCommit(); // Replace with your actual controller/class
        @endphp

        @if ($gitInfo['success'])
            <div class="mx-5 d-flex align-items-center justify-content-center text-muted">
                <i class="tim-icons icon-git-branch mr-2"></i>
                <span class="git-commit-text">

                    <small>Last Commit</small>
                    <span class="mx-2">•</span>
                    <code class="commit-hash-mini">{{ $gitInfo['message'] }}</code>
                    <span class="mx-2">•</span>
                    <small>{{ \Carbon\Carbon::parse($gitInfo['date'])->diffForHumans() }}</small>
                </span>
            </div>
        @else
            <div class="text-center text-muted">
                <i class="tim-icons icon-git-branch mr-1"></i>
                <small>No git info available</small>
            </div>
        @endif


        <div class="copyright">
            &copy; {{ now()->year }} {{ __('made with') }} <i class="tim-icons icon-heart-2"></i> {{ __('by') }}
            <a href="https://egeniuscare.com/" target="_blank">{{ __('eGenuiusCare') }}</a>.
            <br>
            {{ __('Contact support at') }} <a href="mailto:info@egeniuscare.com">info@egeniuscare.com</a>.
        </div>
    </div>
    
</footer>

