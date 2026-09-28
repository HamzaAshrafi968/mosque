<x-permission-matrix
    mode="user"
    :baseline="$roleScopes"
    :overrides="$overrides"
    :exclude="['mosques']"
/>
